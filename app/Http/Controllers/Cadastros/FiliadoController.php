<?php

/*
 * Telas do cadastro de FILIADOS (#37): lista (com filtro por equipe), novo e
 * edição. Só quem tem "filiados.gerenciar" entra (routes/web.php).
 *
 * Cadastrar um filiado cria também a CONTA do app (CriarFiliado). LGPD: a
 * lista nunca leva CPF, nascimento nem telefone para a tela; o formulário de
 * edição leva, porque só quem tem a permissão chega nele.
 */

namespace App\Http\Controllers\Cadastros;

use App\Actions\Filiados\CriarFiliado;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cadastros\EquipeRequest;
use App\Http\Requests\Cadastros\FiliadoRequest;
use App\Models\AreaAtuacao;
use App\Models\Equipe;
use App\Models\Filiado;
use App\Models\Nivel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FiliadoController extends Controller
{
    /**
     * Lista de filiados, 20 por página, com filtro opcional por equipe.
     *
     * @param  Request  $request  Pode trazer ?equipe=3.
     * @return Response A tela "cadastros/filiados/index".
     */
    public function index(Request $request): Response
    {
        $equipe = $request->integer('equipe') ?: null;

        $filiados = Filiado::query()
            ->with(['usuario:id,name', 'equipe:id,nome', 'nivel'])
            ->when($equipe !== null, fn ($consulta) => $consulta->where('equipe_id', $equipe))
            ->orderBy('registro')
            ->paginate(20)
            ->withQueryString()
            // LGPD: só o necessário para identificar (nada de CPF, nascimento ou telefone).
            ->through(fn (Filiado $filiado) => [
                'id' => $filiado->id,
                'registro' => $filiado->registro,
                'nome' => $filiado->usuario?->name,
                'equipe' => $filiado->equipe?->nome,
                'nivel' => $filiado->nivel?->nome(),
                'local' => "{$filiado->municipio}/{$filiado->uf}",
                'status' => $filiado->status,
            ]);

        return Inertia::render('cadastros/filiados/index', [
            'filiados' => $filiados,
            'equipes' => Equipe::query()->orderBy('nome')->get(['id', 'nome']),
            'equipe_escolhida' => $equipe,
        ]);
    }

    /**
     * Formulário vazio para um filiado novo.
     *
     * @return Response A tela do formulário (filiado = null).
     */
    public function create(): Response
    {
        return Inertia::render('cadastros/filiados/formulario', [
            'filiado' => null,
            ...$this->opcoes(),
        ]);
    }

    /**
     * Cria o filiado e a conta do app e volta para a lista.
     *
     * @param  FiliadoRequest  $request  O formulário, já conferido.
     * @param  CriarFiliado  $criar  A ação que cria conta e filiado juntos.
     * @return RedirectResponse Volta para a lista com o número de registro gerado.
     */
    public function store(FiliadoRequest $request, CriarFiliado $criar): RedirectResponse
    {
        /** @var array{nome: string, email: string, cpf: string, nascimento: string, telefone?: string|null, municipio: string, uf: string, equipe_id?: int|null, nivel_id?: int|null, areas?: list<int>} $dados */
        $dados = $request->safe()->except(['status']);
        $filiado = $criar->executar($dados);

        return to_route('cadastros.filiados.index')
            ->with('sucesso', "Filiado cadastrado com o registro {$filiado->registro}.");
    }

    /**
     * Formulário preenchido para editar (com os dados pessoais: só quem tem a permissão chega aqui).
     *
     * @param  Filiado  $filiado  O filiado do endereço (ex.: /cadastros/filiados/3/edit).
     * @return Response A tela do formulário.
     */
    public function edit(Filiado $filiado): Response
    {
        return Inertia::render('cadastros/filiados/formulario', [
            'filiado' => [
                'id' => $filiado->id,
                'registro' => $filiado->registro,
                'nome' => $filiado->usuario?->name,
                'email' => $filiado->usuario?->email,
                'cpf' => $filiado->cpf,
                'nascimento' => $filiado->nascimento,
                'telefone' => $filiado->telefone,
                'municipio' => $filiado->municipio,
                'uf' => $filiado->uf,
                'equipe_id' => $filiado->equipe_id,
                'nivel_id' => $filiado->nivel_id,
                'areas' => $filiado->areas()->pluck('areas_atuacao.id'),
                'status' => $filiado->status,
            ],
            ...$this->opcoes(),
        ]);
    }

    /**
     * Grava as mudanças no filiado e na conta (nome e e-mail) e volta para a lista.
     *
     * @param  FiliadoRequest  $request  O formulário, já conferido.
     * @param  Filiado  $filiado  O filiado que está sendo editado.
     * @return RedirectResponse Volta para a lista com a mensagem de sucesso.
     */
    public function update(FiliadoRequest $request, Filiado $filiado): RedirectResponse
    {
        DB::transaction(function () use ($request, $filiado) {
            // Nome e e-mail ficam na conta do app.
            $filiado->usuario?->update([
                'name' => $request->string('nome')->toString(),
                'email' => $request->string('email')->toString(),
            ]);

            $filiado->update($request->safe()->except(['nome', 'email', 'areas']));
            $filiado->areas()->sync($request->input('areas', []));
        });

        return to_route('cadastros.filiados.index')
            ->with('sucesso', "Filiado {$filiado->registro} atualizado.");
    }

    /**
     * Opções das caixas de escolha do formulário (só o que está ativo).
     *
     * @return array<string, mixed>
     */
    private function opcoes(): array
    {
        return [
            'equipes' => Equipe::query()->orderBy('nome')->get(['id', 'nome']),
            'niveis' => Nivel::emOrdem(Nivel::ativos()->get())
                ->map(fn (Nivel $nivel) => ['id' => $nivel->id, 'nome' => $nivel->nome()])
                ->values(),
            'areas' => AreaAtuacao::ativas()->get(['id', 'nome']),
            'ufs' => EquipeRequest::UFS,
        ];
    }
}
