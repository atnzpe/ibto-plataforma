<?php

/*
 * Telas do cadastro de FILIADOS (#37): lista (com filtro por equipe), novo,
 * edição e reenvio do convite.
 *
 * Quem entra (routes/web.php, Gate "filiados.acessar"):
 * - com "filiados.gerenciar" (Administrador do IBTO): vê e cuida de TODOS;
 * - com "filiados.equipe" (Administrador de equipe): só da PRÓPRIA equipe
 *   (F3b). A equipe dele vem do registro de filiado dele.
 *
 * Cadastrar um filiado cria também a CONTA do app (CriarFiliado) e manda o
 * convite. LGPD: a lista nunca leva CPF, nascimento nem telefone para a tela.
 */

namespace App\Http\Controllers\Cadastros;

use App\Actions\Filiados\CriarFiliado;
use App\Enums\CategoriaFiliado;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cadastros\EquipeRequest;
use App\Http\Requests\Cadastros\FiliadoRequest;
use App\Models\AreaAtuacao;
use App\Models\Equipe;
use App\Models\Filiado;
use App\Models\Nivel;
use App\Models\User;
use App\Notifications\ConviteFiliado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FiliadoController extends Controller
{
    // Número de equipe que não existe: quem é Administrador de equipe mas não
    // tem equipe no próprio registro não vê ninguém (em vez de ver todos).
    private const SEM_EQUIPE = -1;

    /**
     * Lista de filiados, 20 por página, com filtro por equipe.
     * Administrador de equipe vê só a dele, venha o filtro que vier.
     *
     * @param  Request  $request  Pode trazer ?equipe=3.
     * @return Response A tela "cadastros/filiados/index".
     */
    public function index(Request $request): Response
    {
        $limite = $this->equipeLimitada($request);
        // Com limite, o filtro é sempre a própria equipe; sem limite, o que veio no endereço.
        $equipe = $limite ?? ($request->integer('equipe') ?: null);

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
                // Função (ex.: "Instrutor") e graduação (ex.: "Instrutor Expert IV").
                'categoria' => $filiado->categoria?->rotulo(),
                'nivel' => $filiado->nivel?->nome(),
                'local' => "{$filiado->municipio}/{$filiado->uf}",
                'status' => $filiado->status,
            ]);

        return Inertia::render('cadastros/filiados/index', [
            'filiados' => $filiados,
            'equipes' => $this->equipesPermitidas($limite),
            'equipe_escolhida' => $equipe,
        ]);
    }

    /**
     * Formulário vazio para um filiado novo.
     *
     * @return Response A tela do formulário (filiado = null).
     */
    public function create(Request $request): Response
    {
        return Inertia::render('cadastros/filiados/formulario', [
            'filiado' => null,
            ...$this->opcoes($this->equipeLimitada($request)),
        ]);
    }

    /**
     * Cria o filiado e a conta do app (e manda o convite) e volta para a lista.
     * Administrador de equipe só cadastra na própria equipe.
     *
     * @param  FiliadoRequest  $request  O formulário, já conferido.
     * @param  CriarFiliado  $criar  A ação que cria conta e filiado juntos.
     * @return RedirectResponse Volta para a lista com o número de registro gerado.
     */
    public function store(FiliadoRequest $request, CriarFiliado $criar): RedirectResponse
    {
        /** @var array{nome: string, email: string, cpf: string, nascimento: string, telefone?: string|null, municipio: string, uf: string, equipe_id?: int|null, nivel_id?: int|null, areas?: list<int>} $dados */
        $dados = $request->safe()->except(['status']);
        // O formulário manda "0" ou "1"; aqui vira false ou true de verdade.
        // Se não veio, fica de fora e o CriarFiliado usa o padrão.
        if ($request->has('documentacao_emitida')) {
            $dados['documentacao_emitida'] = $request->boolean('documentacao_emitida');
        }

        // Com limite, a equipe é SEMPRE a dele (ignora o que veio do formulário).
        $limite = $this->equipeLimitada($request);
        if ($limite !== null) {
            $dados['equipe_id'] = $limite === self::SEM_EQUIPE ? null : $limite;
        }

        $filiado = $criar->executar($dados);

        return to_route('cadastros.filiados.index')
            ->with('sucesso', "Filiado cadastrado com o registro {$filiado->registro}.");
    }

    /**
     * Formulário preenchido para editar (com os dados pessoais).
     *
     * @param  Filiado  $filiado  O filiado do endereço (ex.: /cadastros/filiados/3/edit).
     * @return Response A tela do formulário.
     */
    public function edit(Request $request, Filiado $filiado): Response
    {
        // Administrador de equipe: só filiados da própria equipe (senão, 403).
        Gate::authorize('filiados.editar', $filiado);

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
                'categoria' => $filiado->categoria?->value,
                'nivel_id' => $filiado->nivel_id,
                'abrangencia' => $filiado->abrangencia,
                'documentacao_emitida' => $filiado->documentacao_emitida,
                'areas' => $filiado->areas()->pluck('areas_atuacao.id'),
                'status' => $filiado->status,
                // true quando a pessoa já criou a senha pelo convite (e-mail confirmado).
                'conta_ativada' => (bool) $filiado->usuario?->hasVerifiedEmail(),
            ],
            ...$this->opcoes($this->equipeLimitada($request)),
            // Quadro da anuidade (#37 + #59), ou null para quem não vê o financeiro.
            'anuidade' => FiliadoAnuidadeController::quadro($filiado, $request->user()),
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
        Gate::authorize('filiados.editar', $filiado);

        $dados = $request->safe()->except(['nome', 'email', 'areas']);

        // Com limite, o filiado não pode ser mandado para outra equipe.
        if ($this->equipeLimitada($request) !== null) {
            $dados['equipe_id'] = $filiado->equipe_id;
        }

        DB::transaction(function () use ($request, $filiado, $dados) {
            // Nome e e-mail ficam na conta do app.
            $filiado->usuario?->update([
                'name' => $request->string('nome')->toString(),
                'email' => $request->string('email')->toString(),
            ]);

            $filiado->update($dados);
            $filiado->areas()->sync($request->input('areas', []));
        });

        return to_route('cadastros.filiados.index')
            ->with('sucesso', "Filiado {$filiado->registro} atualizado.");
    }

    /**
     * Reenvia o convite por e-mail (ex.: o primeiro venceu ou foi para o spam).
     * Se a pessoa já criou a senha, não reenvia: o convite já foi usado.
     *
     * @param  Filiado  $filiado  O filiado do endereço.
     * @return RedirectResponse Volta para a edição com o resultado.
     */
    public function reenviarConvite(Filiado $filiado): RedirectResponse
    {
        Gate::authorize('filiados.editar', $filiado);

        $usuario = $filiado->usuario;

        // Sem conta, ou conta já ativada: nada a reenviar.
        if ($usuario === null || $usuario->hasVerifiedEmail()) {
            return to_route('cadastros.filiados.edit', $filiado)
                ->with('sucesso', 'Este filiado já criou a senha; não é preciso convite.');
        }

        $usuario->notify(new ConviteFiliado);

        return to_route('cadastros.filiados.edit', $filiado)
            ->with('sucesso', 'Convite reenviado. O link novo vale por '.ConviteFiliado::DIAS_DE_VALIDADE.' dias.');
    }

    /**
     * Diz se a pessoa logada está LIMITADA à própria equipe.
     *
     * @return int|null null = sem limite (cuida de todos); senão, o número da equipe
     *                  dela, ou SEM_EQUIPE se ela não tem equipe no registro.
     */
    private function equipeLimitada(Request $request): ?int
    {
        /** @var User $usuario */
        $usuario = $request->user();

        // Cuida de todos os filiados (Administrador do IBTO ou chave de emergência).
        if ($usuario->can('filiados.gerenciar')) {
            return null;
        }

        return $usuario->filiado->equipe_id ?? self::SEM_EQUIPE;
    }

    /**
     * Equipes que a pessoa pode escolher: todas, ou só a dela.
     *
     * @return Collection<int, Equipe>
     */
    private function equipesPermitidas(?int $limite): Collection
    {
        return Equipe::query()
            ->when($limite !== null, fn ($consulta) => $consulta->whereKey($limite))
            ->orderBy('nome')
            ->get(['id', 'nome']);
    }

    /**
     * Opções das caixas de escolha do formulário (só o que está ativo).
     *
     * @return array<string, mixed>
     */
    private function opcoes(?int $limite): array
    {
        return [
            'equipes' => $this->equipesPermitidas($limite),
            'niveis' => Nivel::emOrdem(Nivel::ativos()->get())
                ->map(fn (Nivel $nivel) => ['id' => $nivel->id, 'nome' => $nivel->nome()])
                ->values(),
            'areas' => AreaAtuacao::ativas()->get(['id', 'nome']),
            // As 5 categorias (funções), na ordem da lista: [{valor, rotulo}].
            'categorias' => array_map(
                fn (CategoriaFiliado $categoria) => ['valor' => $categoria->value, 'rotulo' => $categoria->rotulo()],
                CategoriaFiliado::cases(),
            ),
            'ufs' => EquipeRequest::UFS,
        ];
    }
}
