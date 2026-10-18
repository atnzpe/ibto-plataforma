<?php

/*
 * PROVAS DO CURSO — LADO DO INSTRUTOR (#43): a lista das provas com as notas
 * dos alunos e o formulário para montar a prova (perguntas, alternativas e a
 * certa). Quem pode: quem edita o curso (Gate "curso.editar"). As notas dos
 * alunos aparecem só com o registro do filiado ou o nº da matrícula (LGPD).
 */

namespace App\Http\Controllers\Cursos;

use App\Actions\Cursos\Provas;
use App\Enums\SituacaoMatricula;
use App\Exceptions\RegraCurso;
use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\CursoMatricula;
use App\Models\CursoProva;
use App\Models\CursoProvaAlternativa;
use App\Models\CursoProvaQuestao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProvaController extends Controller
{
    /**
     * As provas do curso, cada uma com o resumo e a nota de cada aluno.
     */
    public function index(Curso $curso, Provas $provas): Response
    {
        Gate::authorize('curso.editar', $curso);
        $matriculas = CursoMatricula::query()->with('usuario.filiado')->where('curso_id', $curso->id)->where('situacao', SituacaoMatricula::Confirmada->value)->orderBy('id')->get();

        return Inertia::render('cursos/provas/index', [
            'curso' => ['id' => $curso->id, 'titulo' => $curso->titulo],
            'provas' => $curso->provas()->withCount('questoes')->get()->map(function (CursoProva $prova) use ($matriculas, $provas): array {
                // A situação de cada aluno nesta prova (sem nome: registro ou nº da matrícula).
                $alunos = $matriculas->map(fn (CursoMatricula $matricula) => [
                    'quem' => $matricula->usuario->filiado->registro ?? "Matrícula nº {$matricula->id}",
                    ...$provas->situacao($prova, $matricula),
                ])->values();
                $fizeram = $alunos->whereNotNull('melhor_nota');

                return [
                    'id' => $prova->id,
                    'titulo' => $prova->titulo,
                    'ativa' => $prova->ativa,
                    'questoes' => (int) $prova->getAttribute('questoes_count'),
                    'nota_minima' => $prova->nota_minima,
                    'tentativas_max' => $prova->tentativas_max,
                    'fizeram' => $fizeram->count(),
                    'aprovados' => $fizeram->where('aprovada', true)->count(),
                    // Média das melhores notas de quem fez (null = ninguém fez ainda).
                    'media' => $fizeram->isEmpty() ? null : (int) round((float) $fizeram->avg('melhor_nota')),
                    'alunos' => $alunos,
                ];
            })->values(),
        ]);
    }

    /**
     * Formulário de prova nova (vem com uma pergunta de exemplo vazia).
     */
    public function create(Curso $curso): Response
    {
        Gate::authorize('curso.editar', $curso);

        return Inertia::render('cursos/provas/formulario', ['curso' => ['id' => $curso->id, 'titulo' => $curso->titulo], 'prova' => null]);
    }

    /**
     * Grava a prova nova.
     */
    public function store(Request $request, Curso $curso, Provas $provas): RedirectResponse
    {
        Gate::authorize('curso.editar', $curso);

        return $this->gravar($request, $curso, $provas, null);
    }

    /**
     * Formulário de edição, COM o gabarito (só quem edita o curso chega aqui).
     */
    public function edit(Curso $curso, CursoProva $prova): Response
    {
        Gate::authorize('curso.editar', $curso);
        // A prova precisa ser deste curso (outro número no endereço: 404).
        abort_unless($prova->curso_id === $curso->id, 404);

        return Inertia::render('cursos/provas/formulario', [
            'curso' => ['id' => $curso->id, 'titulo' => $curso->titulo],
            'prova' => [
                'id' => $prova->id,
                'titulo' => $prova->titulo,
                'instrucoes' => $prova->instrucoes ?? '',
                'nota_minima' => $prova->nota_minima,
                'tentativas_max' => $prova->tentativas_max,
                'ativa' => $prova->ativa,
                'questoes' => $prova->questoes()->with('alternativas')->get()->map(fn (CursoProvaQuestao $questao) => [
                    'enunciado' => $questao->enunciado,
                    // Aqui (tela de quem edita) o gabarito precisa aparecer: lido campo a campo.
                    'alternativas' => $questao->alternativas->map(fn (CursoProvaAlternativa $alternativa) => ['texto' => $alternativa->texto, 'correta' => $alternativa->correta])->values(),
                ])->values(),
            ],
        ]);
    }

    /**
     * Grava as alterações da prova.
     */
    public function update(Request $request, Curso $curso, CursoProva $prova, Provas $provas): RedirectResponse
    {
        Gate::authorize('curso.editar', $curso);
        abort_unless($prova->curso_id === $curso->id, 404);

        return $this->gravar($request, $curso, $provas, $prova);
    }

    /**
     * Confere o formulário e grava (nova ou existente).
     */
    private function gravar(Request $request, Curso $curso, Provas $provas, ?CursoProva $prova): RedirectResponse
    {
        $dados = $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'instrucoes' => ['nullable', 'string', 'max:2000'],
            'nota_minima' => ['required', 'integer', 'between:0,100'],
            'tentativas_max' => ['required', 'integer', 'between:1,20'],
            'ativa' => ['required', 'boolean'],
            // De 1 a 100 perguntas; cada uma com 2 a 8 alternativas.
            'questoes' => ['required', 'array', 'min:1', 'max:100'],
            'questoes.*.enunciado' => ['required', 'string', 'max:2000'],
            'questoes.*.alternativas' => ['required', 'array', 'min:2', 'max:8'],
            'questoes.*.alternativas.*.texto' => ['required', 'string', 'max:500'],
            'questoes.*.alternativas.*.correta' => ['required', 'boolean'],
        ], [
            'questoes.required' => 'A prova precisa de pelo menos uma pergunta.',
            'questoes.*.enunciado.required' => 'Escreva o enunciado de todas as perguntas.',
            'questoes.*.alternativas.*.texto.required' => 'Escreva o texto de todas as alternativas.',
        ]);

        // Monta os dados no formato exato que as regras esperam (texto, número e sim/não).
        $limpo = [
            'titulo' => (string) $dados['titulo'],
            'instrucoes' => isset($dados['instrucoes']) ? (string) $dados['instrucoes'] : null,
            'nota_minima' => (int) $dados['nota_minima'],
            'tentativas_max' => (int) $dados['tentativas_max'],
            'ativa' => (bool) $dados['ativa'],
            'questoes' => $this->questoes((array) $dados['questoes']),
        ];

        try {
            $provas->salvar($curso, $limpo, $prova);
        } catch (RegraCurso $erro) {
            return back()->withErrors(['questoes' => "{$erro->getMessage()} (código {$erro->codigo})"]);
        }

        return to_route('comando-geral.cursos.provas.index', $curso)->with('sucesso', 'Prova salva.');
    }

    /**
     * As perguntas do formulário no formato das regras.
     *
     * @param  array<mixed>  $questoes  As perguntas já validadas.
     * @return list<array{enunciado: string, alternativas: list<array{texto: string, correta: bool}>}>
     */
    private function questoes(array $questoes): array
    {
        $saida = [];
        foreach ($questoes as $questao) {
            $questao = (array) $questao;
            $alternativas = [];
            foreach ((array) ($questao['alternativas'] ?? []) as $alternativa) {
                $alternativa = (array) $alternativa;
                $alternativas[] = ['texto' => (string) ($alternativa['texto'] ?? ''), 'correta' => (bool) ($alternativa['correta'] ?? false)];
            }
            $saida[] = ['enunciado' => (string) ($questao['enunciado'] ?? ''), 'alternativas' => $alternativas];
        }

        return $saida;
    }
}
