<?php

/*
 * CURSOS no Comando Geral (#39 / #38, bloco C1): lista com filtros, novo e
 * edição. Não existe "apagar": encerra. Cada mudança vai para a auditoria.
 * Permissão "cursos.gerenciar" (routes/web.php).
 */

namespace App\Http\Controllers\Cursos;

use App\Enums\AcessoCurso;
use App\Enums\ModalidadeCurso;
use App\Enums\SituacaoCurso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cursos\CursoRequest;
use App\Models\AreaAtuacao;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\User;
use App\Support\Dinheiro;
use App\Support\ImagemPublica;
use App\Support\Periodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CursoController extends Controller
{
    // Pasta (dentro de storage/app/public) onde ficam as capas dos cursos.
    private const PASTA_IMAGENS = 'cursos';

    /**
     * Lista com filtros: busca pelo nome, situação e período (pela data de início).
     *
     * @param  Request  $request  Ex.: /comando-geral/cursos?busca=combate&situacao=publicado&de=2026-10-01&ate=2026-12-31
     * @return Response A tela "cursos/index".
     */
    public function index(Request $request): Response
    {
        $busca = trim((string) $request->query('busca', ''));
        $situacao = SituacaoCurso::tryFrom((string) $request->query('situacao', ''));
        // Período só filtra se a pessoa escolher datas (curso online pode não ter data).
        $periodo = $request->filled('de') || $request->filled('ate') ? Periodo::doPedido($request) : null;

        $consulta = Curso::query()->with('area:id,nome')->orderByDesc('data_inicio')->orderBy('titulo');

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
            ]),
            'situacoes' => $this->opcoes(SituacaoCurso::cases()),
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
     * Grava o curso novo (com a capa e a auditoria) e abre a edição dele.
     *
     * @param  CursoRequest  $request  O formulário, já conferido.
     * @return RedirectResponse A lista, com a mensagem.
     */
    public function store(CursoRequest $request): RedirectResponse
    {
        $curso = DB::transaction(function () use ($request): Curso {
            $curso = Curso::create($request->paraGravar());
            ImagemPublica::trocar($curso, 'imagem', self::PASTA_IMAGENS, $request->file('imagem'));
            Auditoria::registrar('CURSO_CRIADO', ['curso_id' => $curso->id, 'situacao' => $curso->situacao->value]);

            return $curso;
        });

        return to_route('comando-geral.cursos.index')->with('sucesso', "Curso \"{$curso->titulo}\" cadastrado.");
    }

    /**
     * Formulário preenchido.
     *
     * @param  Curso  $curso  O do endereço (ex.: /comando-geral/cursos/3/edit).
     * @return Response A tela do formulário.
     */
    public function edit(Curso $curso): Response
    {
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
                'situacao' => $curso->situacao->value,
                'imagem_url' => $curso->imagemUrl(),
            ],
            ...$this->opcoesDoFormulario(),
        ]);
    }

    /**
     * Grava as mudanças (inclusive publicar ou encerrar), com auditoria.
     *
     * @param  CursoRequest  $request  O formulário, já conferido.
     * @param  Curso  $curso  O que está sendo editado.
     * @return RedirectResponse A lista, com a mensagem.
     */
    public function update(CursoRequest $request, Curso $curso): RedirectResponse
    {
        DB::transaction(function () use ($request, $curso): void {
            $antes = $curso->situacao->value;
            $curso->update($request->paraGravar());
            ImagemPublica::trocar($curso, 'imagem', self::PASTA_IMAGENS, $request->file('imagem'));
            Auditoria::registrar('CURSO_EDITADO', ['curso_id' => $curso->id, 'situacao_antes' => $antes, 'situacao_depois' => $curso->situacao->value]);
        });

        return to_route('comando-geral.cursos.index')->with('sucesso', "Curso \"{$curso->titulo}\" atualizado.");
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
            'situacoes' => $this->opcoes(SituacaoCurso::cases()),
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
