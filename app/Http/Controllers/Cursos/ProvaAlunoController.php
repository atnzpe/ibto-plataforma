<?php

/*
 * PROVA — LADO DO ALUNO (#43): ver a prova e responder.
 * - A tela recebe as perguntas e as alternativas SEM o gabarito.
 * - A correção é feita no servidor (App\Actions\Cursos\Provas::responder).
 * - Depois de enviar, o aluno vê a nota e se aprovou; não vê quais errou
 *   (senão a próxima tentativa viraria cópia do gabarito).
 * - Aprovado e com o curso todo concluído: o certificado automático sai na hora.
 * Quem pode: quem tem a matrícula confirmada no curso (Gate "curso.conteudo").
 */

namespace App\Http\Controllers\Cursos;

use App\Actions\Cursos\Certificados;
use App\Actions\Cursos\Provas;
use App\Enums\SituacaoMatricula;
use App\Exceptions\RegraCurso;
use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\CursoMatricula;
use App\Models\CursoProva;
use App\Models\CursoProvaAlternativa;
use App\Models\CursoProvaQuestao;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProvaAlunoController extends Controller
{
    /**
     * A prova para responder (sem gabarito), com a situação do aluno.
     */
    public function show(Request $request, Curso $curso, CursoProva $prova, Provas $provas): Response
    {
        [$matricula] = $this->conferir($request, $curso, $prova);

        return Inertia::render('cursos/provas/fazer', [
            'curso' => ['id' => $curso->id, 'titulo' => $curso->titulo],
            'prova' => [
                'id' => $prova->id,
                'titulo' => $prova->titulo,
                'instrucoes' => $prova->instrucoes,
                'nota_minima' => $prova->nota_minima,
                'tentativas_max' => $prova->tentativas_max,
                // Só o número e o texto: o "correta" (gabarito) nunca vem para a tela.
                'questoes' => $prova->questoes()->with('alternativas')->get()->map(fn (CursoProvaQuestao $questao) => [
                    'id' => $questao->id,
                    'ordem' => $questao->ordem,
                    'enunciado' => $questao->enunciado,
                    'alternativas' => $questao->alternativas->map(fn (CursoProvaAlternativa $alternativa) => ['id' => $alternativa->id, 'texto' => $alternativa->texto])->values(),
                ])->values(),
            ],
            'situacao' => $provas->situacao($prova, $matricula),
        ]);
    }

    /**
     * Recebe as respostas, corrige no servidor e devolve a nota.
     */
    public function responder(Request $request, Curso $curso, CursoProva $prova, Provas $provas, Certificados $certificados): RedirectResponse
    {
        [$matricula, $usuario] = $this->conferir($request, $curso, $prova);
        $dados = $request->validate(['respostas' => ['required', 'array'], 'respostas.*' => ['integer']], [
            'respostas.required' => 'Responda as perguntas antes de enviar.',
        ]);

        try {
            $tentativa = $provas->responder($prova, $matricula, (array) $dados['respostas']);
        } catch (RegraCurso $erro) {
            return back()->withErrors(['respostas' => "{$erro->getMessage()} (código {$erro->codigo})"]);
        }

        // Não aprovou: mostra a nota e quantas tentativas sobram.
        if (! $tentativa->aprovada) {
            $restantes = $provas->situacao($prova, $matricula)['restantes'];

            return back()->with('sucesso', "Sua nota: {$tentativa->nota}. A mínima é {$prova->nota_minima}. Tentativas restantes: {$restantes}.");
        }

        // Aprovou e o curso (só gravado) está todo concluído: o certificado sai na hora.
        if ($certificados->emitirSeConcluiu($curso, $usuario) !== null) {
            return back()->with('sucesso', "Aprovado com nota {$tentativa->nota}! Você concluiu o curso: o certificado está pronto no \"Meu dossiê\".");
        }

        return back()->with('sucesso', "Aprovado com nota {$tentativa->nota}!");
    }

    /**
     * Confere o acesso: a prova é deste curso, está ativa e a pessoa tem matrícula confirmada.
     *
     * @return array{0: CursoMatricula, 1: User}
     */
    private function conferir(Request $request, Curso $curso, CursoProva $prova): array
    {
        Gate::authorize('curso.conteudo', $curso);
        // Prova de outro curso ou desativada: 404 (como se não existisse).
        abort_unless($prova->curso_id === $curso->id && $prova->ativa, 404);

        /** @var User $usuario */
        $usuario = $request->user();
        $matricula = CursoMatricula::query()->where('curso_id', $curso->id)->where('user_id', $usuario->id)->where('situacao', SituacaoMatricula::Confirmada->value)->first();
        // Quem só organiza o curso (sem matrícula) não faz prova: 403.
        abort_if($matricula === null, 403);

        return [$matricula, $usuario];
    }
}
