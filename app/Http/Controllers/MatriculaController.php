<?php

/*
 * MATRÍCULA pelo lado do ALUNO (#41, bloco C5b), na página do curso:
 * - "Garantir minha vaga": cria a matrícula (regras em App\Actions\Cursos\Matriculas);
 * - enviar o COMPROVANTE do PIX (PDF ou foto), guardado no disco PRIVADO;
 * - ver o comprovante: só a própria pessoa e quem confirma pagamentos;
 * - desistir enquanto aguarda o pagamento (cancela sem apagar; a vaga volta).
 * Tudo com login. Cada matrícula só é mexida pela dona (403 para os outros).
 */

namespace App\Http\Controllers;

use App\Actions\Cursos\Matriculas;
use App\Enums\SituacaoMatricula;
use App\Exceptions\RegraCurso;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\CursoMatricula;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MatriculaController extends Controller
{
    /**
     * "Garantir minha vaga": matricula a pessoa logada no curso.
     *
     * @param  Request  $request  Quem está se matriculando.
     * @param  Curso  $curso  O curso do endereço. Ex.: /cursos/7/matricula
     * @param  Matriculas  $matriculas  As regras (o Laravel entrega pronto).
     * @return RedirectResponse Volta para a página do curso com o resultado.
     */
    public function store(Request $request, Curso $curso, Matriculas $matriculas): RedirectResponse
    {
        // Cupom de desconto (#89): opcional, curto (o código tem até 30 letras).
        $dados = $request->validate(['cupom' => ['nullable', 'string', 'max:30']]);

        try {
            $matricula = $matriculas->matricular($curso, $request->user(), cupom: $dados['cupom'] ?? null);
        } catch (RegraCurso $erro) {
            // Regra não deixou (ex.: vagas esgotadas): volta com o motivo e o código. Erro do cupom
            // (códigos CUPOM_...) aparece embaixo do campo do cupom; os outros, no topo do quadro.
            $campo = str_starts_with($erro->codigo, 'CUPOM_') ? 'cupom' : 'matricula';

            return back()->withErrors([$campo => "{$erro->getMessage()} (código {$erro->codigo})"]);
        }

        // Gratuito: já pode assistir. Pago: o próximo passo é o PIX.
        $mensagem = $matricula->situacao === SituacaoMatricula::Confirmada
            ? 'Matrícula confirmada! As aulas já estão liberadas.'
            : 'Vaga reservada! Faça o PIX e envie o comprovante logo abaixo.';

        return to_route('catalogo.curso', $curso->id)->with('sucesso', $mensagem);
    }

    /**
     * Recebe o comprovante do PIX (troca o anterior, se houver).
     *
     * @param  Request  $request  O formulário com o arquivo "comprovante".
     * @param  CursoMatricula  $matricula  A matrícula do endereço.
     * @return RedirectResponse Volta para a página do curso.
     */
    public function comprovante(Request $request, CursoMatricula $matricula): RedirectResponse
    {
        // Só a dona da matrícula envia.
        abort_unless($matricula->user_id === $request->user()?->id, 403);

        // Só enquanto aguarda o pagamento (depois de confirmada ou cancelada, não muda).
        if ($matricula->situacao !== SituacaoMatricula::AguardandoPagamento) {
            return back()->withErrors(['comprovante' => 'Esta matrícula não está aguardando pagamento. (código MATRICULA_NAO_AGUARDANDO)']);
        }

        $request->validate(
            // PDF ou foto, até 5 MB (5120 KB).
            ['comprovante' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']],
            [
                'comprovante.required' => 'Escolha o arquivo do comprovante.',
                'comprovante.mimes' => 'Envie o comprovante em PDF, JPG ou PNG.',
                'comprovante.max' => 'O comprovante pode ter no máximo 5 MB.',
            ],
        );

        $disco = Storage::disk('local');
        // Guarda o novo no disco PRIVADO (fora da pasta pública) e apaga o antigo.
        $caminho = $request->file('comprovante')->store("comprovantes/{$matricula->curso_id}", 'local');
        if ($matricula->comprovante !== null) {
            $disco->delete($matricula->comprovante);
        }
        $matricula->forceFill(['comprovante' => $caminho])->save();

        Auditoria::registrar('COMPROVANTE_ENVIADO', ['matricula_id' => $matricula->id, 'curso_id' => $matricula->curso_id]);

        return to_route('catalogo.curso', $matricula->curso_id)
            ->with('sucesso', 'Comprovante enviado! O IBTO confere e confirma a sua matrícula.');
    }

    /**
     * Mostra o comprovante (abre no navegador).
     *
     * @param  Request  $request  Quem está pedindo.
     * @param  CursoMatricula  $matricula  A matrícula do endereço.
     * @return BinaryFileResponse O arquivo.
     */
    public function verComprovante(Request $request, CursoMatricula $matricula): BinaryFileResponse
    {
        $usuario = $request->user();
        // Só a dona ou quem confirma pagamentos (403 para os outros, mesmo com o link).
        abort_unless($matricula->user_id === $usuario?->id || $usuario?->can('pagamentos.confirmar'), 403);

        $disco = Storage::disk('local');
        // Sem comprovante (ou o arquivo sumiu): 404.
        abort_if($matricula->comprovante === null || ! $disco->exists($matricula->comprovante), 404);

        return response()->file($disco->path($matricula->comprovante));
    }

    /**
     * Desistir enquanto aguarda o pagamento: cancela sem apagar (a vaga volta).
     *
     * @param  Request  $request  Quem está desistindo.
     * @param  CursoMatricula  $matricula  A matrícula do endereço.
     * @param  Matriculas  $matriculas  As regras.
     * @return RedirectResponse Volta para a página do curso.
     */
    public function desistir(Request $request, CursoMatricula $matricula, Matriculas $matriculas): RedirectResponse
    {
        abort_unless($matricula->user_id === $request->user()?->id, 403);

        // Matrícula já paga: a desistência passa pelo IBTO (devolução do dinheiro).
        if ($matricula->situacao !== SituacaoMatricula::AguardandoPagamento) {
            return back()->withErrors(['matricula' => 'Para cancelar uma matrícula já confirmada, fale com o IBTO. (código MATRICULA_CONFIRMADA)']);
        }

        $matriculas->cancelar($matricula, 'Desistência do aluno antes do pagamento');

        return to_route('catalogo.curso', $matricula->curso_id)->with('sucesso', 'Matrícula cancelada. A vaga foi liberada.');
    }
}
