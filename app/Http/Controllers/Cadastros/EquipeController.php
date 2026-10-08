<?php

/*
 * Telas do cadastro de equipes filiadas (#37): lista, nova equipe e edição.
 *
 * Sem "apagar": a situação "Inativa" tira a equipe das escolhas sem perder o
 * histórico dos filiados. Só administradores entram (Gate "administrar").
 * LGPD: telefone, e-mail e documento do responsável só aparecem no formulário
 * de edição (para o administrador), nunca na lista.
 */

namespace App\Http\Controllers\Cadastros;

use App\Enums\StatusEquipe;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cadastros\EquipeRequest;
use App\Models\AreaAtuacao;
use App\Models\Equipe;
use App\Support\ImagemPublica;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EquipeController extends Controller
{
    // Pasta (dentro de storage/app/public) onde ficam as logos.
    private const PASTA_LOGOS = 'equipes';

    /**
     * Lista das equipes em ordem de nome, SEM contato nem documento (LGPD).
     *
     * @return Response A tela "cadastros/equipes/index".
     */
    public function index(): Response
    {
        return Inertia::render('cadastros/equipes/index', [
            'equipes' => Equipe::query()->with('areas:id,nome')->orderBy('nome')->get()
                ->map(fn (Equipe $equipe) => [
                    'id' => $equipe->id,
                    'nome' => $equipe->nome,
                    'logo_url' => $equipe->logoUrl(),
                    // Ex.: "Recife/PE"; null enquanto não tem cidade.
                    'local' => $equipe->cidade !== null ? "{$equipe->cidade}/{$equipe->uf}" : null,
                    'areas' => $equipe->areas->pluck('nome'),
                    'status' => $equipe->status->value,
                    'status_rotulo' => $equipe->status->rotulo(),
                ]),
        ]);
    }

    /**
     * Formulário vazio para uma equipe nova.
     *
     * @return Response A tela do formulário (equipe = null).
     */
    public function create(): Response
    {
        return Inertia::render('cadastros/equipes/formulario', [
            'equipe' => null,
            ...$this->opcoes(),
        ]);
    }

    /**
     * Grava a equipe nova (com áreas e logo) e volta para a lista.
     *
     * @param  EquipeRequest  $request  O formulário, já conferido.
     * @return RedirectResponse Volta para a lista com a mensagem de sucesso.
     */
    public function store(EquipeRequest $request): RedirectResponse
    {
        // "transaction": equipe e áreas são gravadas juntas; se uma parte falhar, nada fica pela metade.
        $equipe = DB::transaction(function () use ($request) {
            $equipe = Equipe::create($this->dados($request));
            $equipe->areas()->sync($request->input('areas', []));

            return $equipe;
        });

        ImagemPublica::trocar($equipe, 'logo', self::PASTA_LOGOS, $request->file('logo'));

        return to_route('cadastros.equipes.index')
            ->with('sucesso', "Equipe \"{$equipe->nome}\" cadastrada.");
    }

    /**
     * Formulário preenchido para editar a equipe (com contato e documento: só o administrador chega aqui).
     *
     * @param  Equipe  $equipe  A equipe do endereço (ex.: /cadastros/equipes/3/edit).
     * @return Response A tela do formulário.
     */
    public function edit(Equipe $equipe): Response
    {
        return Inertia::render('cadastros/equipes/formulario', [
            'equipe' => [
                'id' => $equipe->id,
                'nome' => $equipe->nome,
                'logo_url' => $equipe->logoUrl(),
                'cidade' => $equipe->cidade,
                'uf' => $equipe->uf,
                'responsavel_nome' => $equipe->responsavel_nome,
                'responsavel_telefone' => $equipe->responsavel_telefone,
                'responsavel_email' => $equipe->responsavel_email,
                'documento' => $equipe->documento,
                'status' => $equipe->status->value,
                'areas' => $equipe->areas()->pluck('areas_atuacao.id'),
            ],
            ...$this->opcoes(),
        ]);
    }

    /**
     * Grava as mudanças (inclusive a situação e as áreas) e volta para a lista.
     *
     * @param  EquipeRequest  $request  O formulário, já conferido.
     * @param  Equipe  $equipe  A equipe que está sendo editada.
     * @return RedirectResponse Volta para a lista com a mensagem de sucesso.
     */
    public function update(EquipeRequest $request, Equipe $equipe): RedirectResponse
    {
        DB::transaction(function () use ($request, $equipe) {
            $equipe->update($this->dados($request));
            // sync: deixa a equipe exatamente com as áreas marcadas (tira as desmarcadas).
            $equipe->areas()->sync($request->input('areas', []));
        });

        ImagemPublica::trocar($equipe, 'logo', self::PASTA_LOGOS, $request->file('logo'));

        return to_route('cadastros.equipes.index')
            ->with('sucesso', "Equipe \"{$equipe->nome}\" atualizada.");
    }

    /**
     * Os campos de texto conferidos (sem áreas e sem logo, que são gravadas à parte).
     *
     * @return array<string, mixed>
     */
    private function dados(EquipeRequest $request): array
    {
        return $request->safe()->except(['areas', 'logo']);
    }

    /**
     * Opções das caixas de escolha do formulário.
     *
     * @return array{situacoes: list<array{valor: string, rotulo: string}>, ufs: list<string>, areas: mixed}
     */
    private function opcoes(): array
    {
        return [
            'situacoes' => array_map(
                fn (StatusEquipe $status) => ['valor' => $status->value, 'rotulo' => $status->rotulo()],
                StatusEquipe::cases(),
            ),
            'ufs' => EquipeRequest::UFS,
            // Só as áreas ATIVAS aparecem para escolher (o filtro escrito pelo Gleyson).
            'areas' => AreaAtuacao::ativas()->get(['id', 'nome']),
        ];
    }
}
