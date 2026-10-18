<?php

/*
 * CERTIFICADO DE GRADUAÇÃO (#46, parte 2): emitir e cancelar (na ficha do
 * filiado, só quem cuida de TODOS os filiados — "filiados.gerenciar") e ver
 * para imprimir / salvar em PDF (o próprio filiado ou quem gerencia).
 * A validação pública pelo QR fica no CertificadoController::validar (a mesma
 * página /validar atende os certificados de curso e de graduação).
 */

namespace App\Http\Controllers\Cadastros;

use App\Actions\Filiados\CertificadosGraduacao;
use App\Exceptions\RegraFinanceira;
use App\Http\Controllers\Controller;
use App\Models\Filiado;
use App\Models\GraduacaoCertificado;
use App\Support\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GraduacaoController extends Controller
{
    /**
     * Emite o certificado com os textos confirmados no formulário.
     */
    public function store(Request $request, Filiado $filiado, CertificadosGraduacao $graduacoes): RedirectResponse
    {
        abort_unless($request->user()->temPermissao('filiados.gerenciar'), 403);
        $dados = $request->validate([
            'condicao' => ['required', 'string', 'max:150'],
            'nivel' => ['required', 'string', 'max:100'],
            'abrangencia' => ['required', 'string', 'max:60'],
            'local' => ['required', 'string', 'max:100'],
        ]);

        try {
            $certificado = $graduacoes->emitir($filiado, ['condicao' => (string) $dados['condicao'], 'nivel' => (string) $dados['nivel'], 'abrangencia' => (string) $dados['abrangencia'], 'local' => (string) $dados['local']]);
        } catch (RegraFinanceira $erro) {
            return back()->withErrors(['graduacao' => "{$erro->getMessage()} (código {$erro->codigo})"]);
        }

        return back()->with('sucesso', "Certificado de graduação {$certificado->numero_serie} emitido.");
    }

    /**
     * Cancela com motivo (fica no histórico; a validação pública mostra "cancelado").
     */
    public function cancelar(Request $request, GraduacaoCertificado $certificado, CertificadosGraduacao $graduacoes): RedirectResponse
    {
        abort_unless($request->user()->temPermissao('filiados.gerenciar'), 403);
        $dados = $request->validate(['motivo' => ['required', 'string', 'max:500']], ['motivo.required' => 'Explique o motivo do cancelamento.']);
        $graduacoes->cancelar($certificado, (string) $dados['motivo']);

        return back()->with('sucesso', "Certificado {$certificado->numero_serie} cancelado.");
    }

    /**
     * O certificado para imprimir / salvar em PDF (o dono ou quem gerencia os filiados).
     */
    public function ver(Request $request, string $codigo): Response
    {
        $certificado = GraduacaoCertificado::with('filiado')->where('codigo', $codigo)->firstOrFail();
        abort_unless($certificado->filiado->user_id === $request->user()?->id || $request->user()?->temPermissao('filiados.gerenciar'), 403);
        // O endereço da validação pública: vai impresso e dentro do QR code.
        $validarUrl = route('certificados.validar', $certificado->codigo);

        return Inertia::render('graduacoes/ver', [
            'certificado' => [
                'codigo' => $certificado->codigo,
                'numero_serie' => $certificado->numero_serie,
                'nome' => $certificado->nome,
                'condicao' => $certificado->condicao,
                'nivel' => $certificado->nivel,
                'abrangencia' => $certificado->abrangencia,
                'local' => $certificado->local,
                'emitido_em' => $certificado->emitido_em->toDateString(),
                'valido' => $certificado->valido(),
                'validar_url' => $validarUrl,
                'qr_svg' => QrCode::svg($validarUrl),
                'presidente' => (string) config('ibto.presidente_nome'),
                'vice' => (string) config('ibto.vice_nome'),
            ],
        ])->withViewData(['noindex' => true]);
    }

    /**
     * Os dados da ficha do filiado (só para quem gerencia): a sugestão e os certificados.
     *
     * @return array{sugestao: array<string, string>, certificados: list<array<string, mixed>>}|null
     */
    public static function quadro(Filiado $filiado, Request $request): ?array
    {
        // Só quem cuida de todos os filiados emite e vê a lista.
        if (! $request->user()->temPermissao('filiados.gerenciar')) {
            return null;
        }

        return [
            'sugestao' => app(CertificadosGraduacao::class)->sugestao($filiado),
            'certificados' => array_values(GraduacaoCertificado::where('filiado_id', $filiado->id)->orderByDesc('id')->get()
                ->map(fn (GraduacaoCertificado $certificado): array => [
                    'id' => $certificado->id,
                    'codigo' => $certificado->codigo,
                    'numero_serie' => $certificado->numero_serie,
                    'condicao' => $certificado->condicao,
                    'nivel' => $certificado->nivel,
                    'emitido_em' => $certificado->emitido_em->format('d/m/Y'),
                    'valido' => $certificado->valido(),
                    'motivo_cancelamento' => $certificado->motivo_cancelamento,
                ])->all()),
        ];
    }
}
