<?php

/*
 * CURSOS (#39 / #38 / #40): lista com filtros, novo, edição e as ações do FLUXO
 * de chancela (enviar, chancelar, rejeitar, cancelar, encerrar).
 * - Comando Geral (cursos.gerenciar): vê e mexe em todos;
 * - quem PROPÕE (cursos.propor: instrutor, admin de equipe): vê e mexe só nos
 *   seus, e só publica depois da CHANCELA do IBTO (decisão do Gleyson, 10/10/2026).
 * A situação muda só pelas ações do fluxo (App\Services\FluxoCurso), nunca pelo
 * formulário. Não existe "apagar". Cada mudança vai para a auditoria.
 */

namespace App\Http\Controllers\Cursos;

use App\Enums\AcessoCurso;
use App\Enums\ModalidadeCurso;
use App\Enums\SituacaoCurso;
use App\Exceptions\RegraCurso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cursos\CursoRequest;
use App\Models\AreaAtuacao;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\User;
use App\Services\FluxoCurso;
use App\Support\Dinheiro;
use App\Support\ImagemPublica;
use App\Support\Periodo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CursoController extends Controller
{
    // Pasta (dentro de storage/app/public) onde ficam as capas dos cursos.
    private const PASTA_IMAGENS = 'cursos';

    /**
     * Lista com filtros: busca pelo nome, situação e período (pela data de início).
     *
     * @param  Request  $request  Ex.: /comando-geral/cursos?busca=combate&situacao=em_analise&de=2026-10-01&ate=2026-12-31
     * @return Response A tela "cursos/index".
     */
    public function index(Request $request): Response
    {
        $busca = trim((string) $request->query('busca', ''));
        $situacao = SituacaoCurso::tryFrom((string) $request->query('situacao', ''));
        // Período só filtra se a pessoa escolher datas (curso online pode não ter data).
        $periodo = $request->filled('de') || $request->filled('ate') ? Periodo::doPedido($request) : null;

        $consulta = $this->visiveis($request->user())->with('area:id,nome')->orderByDesc('data_inicio')->orderBy('titulo');

        // Busca pelo nome (sem diferenciar maiúscula/minúscula).
        if ($busca !== '') {
            $consulta->where('titulo', 'like', '%'.$busca.'%');
        }
        if ($situacao !== null) {
            $consulta->where('situacao', $situacao);
        }
        if ($periodo !== null) {
            $consulta->whereBetween('data_inicio', [$periodo->inicio->toDateString(), $periodo->fim->toDateString()]);
        }

        return Inertia::render('cursos/index', [
            'filtros' => [
                'busca' => $busca,
                // O "??" já protege: situação vazia não dá erro, vira ''.
                'situacao' => $situacao->value ?? '',
                'de' => $periodo?->inicio->toDateString() ?? '',
                'ate' => $periodo?->fim->toDateString() ?? '',
            ],
            'cursos' => $consulta->limit(300)->get()->map(fn (Curso $curso) => [
                'id' => $curso->id,
                'titulo' => $curso->titulo,
                'area' => $curso->area?->nome,
                'modalidade' => $curso->modalidade->rotulo(),
                'tipo' => $curso->tipo(),
                'valor_centavos' => $curso->valor_centavos,
                'data_inicio' => $curso->data_inicio?->toDateString(),
                'vagas' => $curso->vagas,
                'situacao' => $curso->situacao->value,
                'situacao_rotulo' => $curso->situacao->rotulo(),
                // Pode abrir a edição? (quem propôs: só rascunho/rejeitado.)
                'pode_editar' => Gate::allows('curso.editar', $curso),
            ]),
            'situacoes' => $this->opcoes(SituacaoCurso::cases()),
            // Quantos esperam a chancela (aparece como aviso para quem chancela).
            'em_analise' => Gate::allows('cursos.chancelar') || Gate::allows('cursos.gerenciar')
                ? Curso::where('situacao', SituacaoCurso::EmAnalise)->count()
                : 0,
            'pode_cadastrar_trilhas' => Gate::allows('cursos.gerenciar'),
        ]);
    }

    /**
     * Formulário vazio.
     *
     * @return Response A tela "cursos/formulario".
     */
    public function create(): Response
    {
        return Inertia::render('cursos/formulario', ['curso' => null, ...$this->opcoesDoFormulario()]);
    }

    /**
     * Grava o curso novo como RASCUNHO (com a capa, quem propôs e a auditoria).
     *
     * @param  CursoRequest  $request  O formulário, já conferido.
     * @return RedirectResponse A edição do curso, para seguir com o conteúdo e o envio.
     */
    public function store(CursoRequest $request): RedirectResponse
    {
        $curso = DB::transaction(function () use ($request): Curso {
            $curso = new Curso($request->paraGravar());
            $curso->forceFill([
                // Todo curso nasce rascunho: publicar é uma ação do fluxo (chancela).
                'situacao' => SituacaoCurso::Rascunho,
                // Quem propôs; para o instrutor/admin de equipe, é o "dono" do curso.
                'responsavel_id' => $request->user()?->id,
                // Curso de equipe: a equipe de quem propôs (vazio para o IBTO).
                'equipe_id' => $request->user()?->can('cursos.gerenciar') ? null : $request->user()?->filiado?->equipe_id,
            ])->save();
            ImagemPublica::trocar($curso, 'imagem', self::PASTA_IMAGENS, $request->file('imagem'));
            Auditoria::registrar('CURSO_CRIADO', ['curso_id' => $curso->id, 'situacao' => $curso->situacao->value]);

            return $curso;
        });

        return to_route('comando-geral.cursos.edit', $curso)->with('sucesso', "Curso \"{$curso->titulo}\" cadastrado como rascunho.");
    }

    /**
     * Edição: o formulário (se pode editar) e o painel da situação com as ações
     * que ESTA pessoa pode fazer agora.
     *
     * @param  Request  $request  Quem está vendo.
     * @param  Curso  $curso  O do endereço (ex.: /comando-geral/cursos/3/edit).
     * @param  FluxoCurso  $fluxo  As regras do fluxo de chancela.
     */
    public function edit(Request $request, Curso $curso, FluxoCurso $fluxo): Response
    {
        // Só vê quem pode: o Comando Geral, quem chancela, ou o dono do curso.
        abort_unless(Gate::allows('curso.conteudo', $curso), 403);

        return Inertia::render('cursos/formulario', [
            'curso' => [
                'id' => $curso->id,
                'titulo' => $curso->titulo,
                'descricao' => $curso->descricao,
                'area_atuacao_id' => $curso->area_atuacao_id,
                'modalidade' => $curso->modalidade->value,
                'acesso' => $curso->acesso->value,
                // Valor no formato do campo; gratuito = vazio. Ex.: 9000 → '90,00'.
                'valor' => $curso->gratuito() ? '' : Dinheiro::paraCampo($curso->valor_centavos),
                'vagas' => $curso->vagas,
                'data_inicio' => $curso->data_inicio?->toDateString(),
                'data_fim' => $curso->data_fim?->toDateString(),
                // O banco guarda "08:00:00"; o campo usa "08:00".
                'hora_inicio' => $curso->hora_inicio !== null ? substr($curso->hora_inicio, 0, 5) : null,
                'hora_fim' => $curso->hora_fim !== null ? substr($curso->hora_fim, 0, 5) : null,
                'local' => $curso->local,
                'carga_horaria' => $curso->carga_horaria,
                'instrutor_id' => $curso->instrutor_id,
                'link_pagamento' => $curso->link_pagamento,
                'material_incluso' => $curso->material_incluso,
                'instrutor_nome' => $curso->instrutor_nome,
                'instrutor_especialidade' => $curso->instrutor_especialidade,
                'chamada' => $curso->chamada,
                'contato_whatsapp' => $curso->contato_whatsapp,
                'imagem_url' => $curso->imagemUrl(),
                'situacao' => $curso->situacao->value,
                'situacao_rotulo' => $curso->situacao->rotulo(),
                'motivo_situacao' => $curso->motivo_situacao,
                'chancelado_em' => $curso->chancelado_em?->format('d/m/Y H:i'),
                // Pode salvar os dados? (o dono, só em rascunho/rejeitado.)
                'pode_editar' => Gate::allows('curso.editar', $curso),
                // Os botões do fluxo que esta pessoa pode usar agora.
                'acoes' => $fluxo->acoesPara($curso, $request->user()),
            ],
            ...$this->opcoesDoFormulario(),
        ]);
    }

    /**
     * Grava as mudanças dos DADOS (a situação não muda aqui), com auditoria.
     *
     * @param  CursoRequest  $request  O formulário, já conferido.
     * @param  Curso  $curso  O que está sendo editado.
     */
    public function update(CursoRequest $request, Curso $curso): RedirectResponse
    {
        Gate::authorize('curso.editar', $curso);

        DB::transaction(function () use ($request, $curso): void {
            $curso->update($request->paraGravar());
            ImagemPublica::trocar($curso, 'imagem', self::PASTA_IMAGENS, $request->file('imagem'));
            Auditoria::registrar('CURSO_EDITADO', ['curso_id' => $curso->id, 'situacao' => $curso->situacao->value]);
        });

        return back()->with('sucesso', "Curso \"{$curso->titulo}\" atualizado.");
    }

    /**
     * Faz uma ação do fluxo de chancela (enviar, chancelar, rejeitar, cancelar, encerrar).
     *
     * @param  Request  $request  Com "acao" e, quando pede, "motivo".
     * @param  Curso  $curso  O curso.
     * @param  FluxoCurso  $fluxo  As regras do fluxo.
     * @return RedirectResponse De volta, com a mensagem ou o erro da regra (com código).
     */
    public function situacao(Request $request, Curso $curso, FluxoCurso $fluxo): RedirectResponse
    {
        try {
            $fluxo->aplicar($curso, $request->user(), (string) $request->input('acao'), $request->input('motivo'));
        } catch (RegraCurso $erro) {
            return back()->withErrors(['acao' => "{$erro->getMessage()} ({$erro->codigo})"]);
        }

        return back()->with('sucesso', "Curso agora: {$curso->situacao->rotulo()}.");
    }

    /**
     * Os cursos que esta pessoa pode ver na lista: todos (Comando Geral ou quem
     * chancela) ou só os que ela propôs.
     *
     * @return Builder<Curso>
     */
    private function visiveis(?User $usuario): Builder
    {
        $consulta = Curso::query();

        if ($usuario === null || ! ($usuario->can('cursos.gerenciar') || $usuario->can('cursos.chancelar'))) {
            $consulta->where('responsavel_id', $usuario?->id);
        }

        return $consulta;
    }

    /**
     * As listas de escolha do formulário.
     *
     * @return array<string, mixed>
     */
    private function opcoesDoFormulario(): array
    {
        return [
            'areas' => AreaAtuacao::query()->where('ativa', true)->orderBy('nome')->get(['id', 'nome']),
            // Instrutores: contas com o papel "instrutor".
            'instrutores' => User::query()
                ->whereHas('papeis', fn ($papel) => $papel->where('slug', 'instrutor'))
                ->orderBy('name')->get(['id', 'name']),
            'modalidades' => $this->opcoes(ModalidadeCurso::cases()),
            'acessos' => $this->opcoes(AcessoCurso::cases()),
        ];
    }

    /**
     * Opções de uma lista fixa (enum) para a tela. Ex.: [['valor' => 'online', 'rotulo' => 'Online']]
     *
     * @param  list<ModalidadeCurso|AcessoCurso|SituacaoCurso>  $casos
     * @return list<array{valor: string, rotulo: string}>
     */
    private function opcoes(array $casos): array
    {
        return array_map(fn ($caso) => ['valor' => $caso->value, 'rotulo' => $caso->rotulo()], $casos);
    }
}
