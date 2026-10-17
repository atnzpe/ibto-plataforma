<?php

/*
 * CERTIFICADOS (#44):
 * - emitir / emitirAptos / cancelar: no Comando Geral, só o IBTO
 *   (Gate "certificados.emitir"); regras em App\Actions\Cursos\Certificados;
 * - ver: a página do certificado para imprimir ("Salvar como PDF"), com o
 *   QR code que leva à validação (App\Support\QrCode), para o dono do
 *   certificado ou quem vê os inscritos do curso;
 * - validar: PÚBLICA, pelo código; mostra só o necessário (curso, datas,
 *   carga horária e o NOME ABREVIADO). Código inventado: "não encontrado".
 *   Certificado cancelado: "cancelado" (deixa de validar).
 */

namespace App\Http\Controllers;

use App\Actions\Cursos\Certificados;
use App\Enums\ModalidadeCurso;
use App\Exceptions\RegraCurso;
use App\Models\Curso;
use App\Models\CursoCertificado;
use App\Models\CursoMatricula;
use App\Support\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CertificadoController extends Controller
{
    /**
     * Emite o certificado de uma matrícula.
     *
     * @param  Curso  $curso  O curso do endereço.
     * @param  CursoMatricula  $matricula  A matrícula (precisa ser deste curso).
     * @param  Certificados  $certificados  As regras.
     * @return RedirectResponse Volta para os detalhes do curso.
     */
    public function emitir(Curso $curso, CursoMatricula $matricula, Certificados $certificados): RedirectResponse
    {
        Gate::authorize('certificados.emitir');
        abort_if($matricula->curso_id !== $curso->id, 404);

        try {
            $certificado = $certificados->emitir($matricula);
        } catch (RegraCurso $erro) {
            return back()->withErrors(['certificado' => "{$erro->getMessage()} (código {$erro->codigo})"]);
        }

        return back()->with('sucesso', "Certificado {$certificado->codigo} emitido.");
    }

    /**
     * Emite os certificados de TODOS os aptos que ainda não têm ("Emitir certificados dos presentes").
     *
     * @param  Curso  $curso  O curso do endereço.
     * @param  Certificados  $certificados  As regras.
     * @return RedirectResponse Volta com quantos foram emitidos e quantos não estavam aptos.
     */
    public function emitirAptos(Curso $curso, Certificados $certificados): RedirectResponse
    {
        Gate::authorize('certificados.emitir');

        $emitidos = 0;
        $naoAptos = 0;
        // Só as confirmadas e sem certificado.
        $pendentes = $curso->matriculas()->where('situacao', 'confirmada')->whereDoesntHave('certificado')->get();
        foreach ($pendentes as $matricula) {
            if ($certificados->apto($matricula)['apto']) {
                $certificados->emitir($matricula);
                $emitidos++;
            } else {
                $naoAptos++;
            }
        }

        return back()->with('sucesso', "{$emitidos} certificado(s) emitido(s)."
            .($naoAptos > 0 ? " {$naoAptos} inscrito(s) sem a presença mínima de ".Certificados::FREQUENCIA_MINIMA.'%.' : ''));
    }

    /**
     * Cancela um certificado (com motivo; deixa de validar).
     *
     * @param  Request  $request  Ex.: motivo='Emitido por engano'
     * @param  CursoCertificado  $certificado  O do endereço.
     * @param  Certificados  $certificados  As regras.
     * @return RedirectResponse Volta com o resultado.
     */
    public function cancelar(Request $request, CursoCertificado $certificado, Certificados $certificados): RedirectResponse
    {
        Gate::authorize('certificados.emitir');

        try {
            $certificados->cancelar($certificado, (string) $request->input('motivo', ''));
        } catch (RegraCurso $erro) {
            return back()->withErrors(['certificado' => "{$erro->getMessage()} (código {$erro->codigo})"]);
        }

        return back()->with('sucesso', "Certificado {$certificado->codigo} cancelado: ele deixa de validar.");
    }

    /**
     * A página do certificado para imprimir / salvar em PDF.
     *
     * @param  Request  $request  Quem está vendo.
     * @param  string  $codigo  Ex.: 'IBTO-7K2M-9QXA'
     * @return Response A tela "certificados/ver".
     */
    public function ver(Request $request, string $codigo): Response
    {
        $certificado = CursoCertificado::where('codigo', $codigo)->with('matricula.curso')->firstOrFail();
        $matricula = $certificado->matricula;

        // O dono do certificado, ou quem vê os inscritos do curso (Comando Geral, dono do curso...).
        abort_unless(
            $matricula->user_id === $request->user()?->id || Gate::allows('curso.inscritos', $matricula->curso),
            403,
        );

        // O endereço da validação pública: vai impresso e dentro do QR code.
        $validarUrl = route('certificados.validar', $certificado->codigo);

        return Inertia::render('certificados/ver', [
            'certificado' => [
                'codigo' => $certificado->codigo,
                'nome' => $certificado->nome,
                'curso' => $certificado->curso,
                'carga_horaria' => $certificado->carga_horaria,
                'instrutor' => $certificado->instrutor,
                // Onde foi o curso (rodapé do modelo institucional): o local do
                // curso, ou "Online". Ex.: 'Recife - PE'. Sem local: null (o campo some).
                'local' => $matricula->curso->modalidade === ModalidadeCurso::Online ? 'Online' : $matricula->curso->local,
                'concluido_em' => $certificado->concluido_em->toDateString(),
                'emitido_em' => $certificado->emitido_em->toDateString(),
                'valido' => $certificado->valido(),
                'validar_url' => $validarUrl,
                // O QR code (desenho SVG) que leva à validação: quem recebe o
                // certificado aponta a câmera do celular e confere na hora.
                'qr_svg' => QrCode::svg($validarUrl),
            ],
        ]);
    }

    /**
     * VALIDAÇÃO PÚBLICA pelo código (sem login). Mostra só o necessário.
     *
     * @param  string  $codigo  O código digitado ou lido no QR. Ex.: 'IBTO-7K2M-9QXA'
     * @return Response A tela "certificados/validar".
     */
    public function validar(string $codigo): Response
    {
        // Aceita minúsculas e espaços (como a pessoa digitar).
        $certificado = CursoCertificado::where('codigo', strtoupper(trim($codigo)))->first();

        return Inertia::render('certificados/validar', [
            'codigo' => strtoupper(trim($codigo)),
            // null = não encontrado (código inventado ou errado).
            'certificado' => $certificado === null ? null : [
                'valido' => $certificado->valido(),
                'nome' => $certificado->nomeAbreviado(),
                'curso' => $certificado->curso,
                'carga_horaria' => $certificado->carga_horaria,
                'concluido_em' => $certificado->concluido_em->toDateString(),
                'emitido_em' => $certificado->emitido_em->toDateString(),
                'cancelado_em' => $certificado->cancelado_em?->toDateString(),
            ],
            // noindex (#74): a validação mostra o nome (abreviado) do aluno e não deve ir para o Google.
        ])->withViewData(['noindex' => true]);
    }
}
