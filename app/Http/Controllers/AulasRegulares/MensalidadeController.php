<?php

/*
 * MENSALIDADES DAS AULAS REGULARES NO COMANDO GERAL (#80): a fila do que está
 * aguardando o Pix (com o identificador IBTOAUL<número> para achar no extrato),
 * "Confirmar pagamento" e as assinaturas de cada programa (com cancelar).
 * Quem confirma: pagamentos.confirmar (o mesmo das matrículas de curso).
 * LGPD: só o nome de quem assina (nada de CPF, e-mail ou telefone).
 */

namespace App\Http\Controllers\AulasRegulares;

use App\Actions\AulasRegulares\Assinaturas;
use App\Exceptions\RegraCurso;
use App\Http\Controllers\Controller;
use App\Models\Assinatura;
use App\Models\AssinaturaPagamento;
use App\Models\FinFormaPagamento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MensalidadeController extends Controller
{
    /** Prefixo do identificador do Pix das mensalidades (aparece no extrato). */
    public const PREFIXO_TXID = 'IBTOAUL';

    /**
     * O identificador do Pix de uma mensalidade. Ex.: 42 → 'IBTOAUL42'.
     */
    public static function txid(int $pagamentoId): string
    {
        return self::PREFIXO_TXID.$pagamentoId;
    }

    /**
     * A fila das mensalidades aguardando e as assinaturas.
     */
    public function index(): Response
    {
        return Inertia::render('aulas-regulares/admin/mensalidades', [
            'aguardando' => AssinaturaPagamento::query()->with('assinatura.programa:id,titulo', 'assinatura.usuario:id,name')
                ->where('situacao', 'aguardando')->orderBy('vence_em')->limit(200)->get()
                ->map(fn (AssinaturaPagamento $p) => [
                    'id' => $p->id,
                    'txid' => self::txid($p->id),
                    'programa' => $p->assinatura->programa->titulo,
                    'pessoa' => $p->assinatura->usuario->name,
                    'referencia' => $p->referencia,
                    'valor_centavos' => $p->valor_centavos,
                    'vence_em' => $p->vence_em->format('d/m/Y'),
                ])->values()->all(),
            'assinaturas' => Assinatura::query()->with('programa:id,titulo', 'usuario:id,name')
                ->where('situacao', '!=', 'cancelada')->latest('id')->limit(300)->get()
                ->map(fn (Assinatura $a) => [
                    'id' => $a->id,
                    'programa' => $a->programa->titulo,
                    'pessoa' => $a->usuario->name,
                    'acesso' => $a->acesso(),
                    'pago_ate' => $a->pago_ate?->format('d/m/Y'),
                ])->values()->all(),
        ]);
    }

    /**
     * Confirma o Pix de uma mensalidade (receita + repasse no livro-caixa).
     */
    public function confirmar(Request $request, AssinaturaPagamento $pagamento, Assinaturas $assinaturas): RedirectResponse
    {
        $dados = $request->validate(['pago_em' => ['required', 'date', 'before_or_equal:today']]);
        $pix = (int) FinFormaPagamento::where('nome', 'PIX')->value('id');

        try {
            $assinaturas->confirmarPagamento($pagamento, $dados['pago_em'], $pix);
        } catch (RegraCurso $erro) {
            return back()->withErrors(['mensalidade' => "{$erro->getMessage()} (código {$erro->codigo})"]);
        }

        return back()->with('sucesso', 'Pagamento confirmado: a receita e o repasse ao instrutor estão no livro-caixa.');
    }

    /**
     * Cancela uma assinatura (o histórico fica).
     */
    public function cancelar(Assinatura $assinatura, Assinaturas $assinaturas): RedirectResponse
    {
        $assinaturas->cancelar($assinatura);

        return back()->with('sucesso', 'Assinatura cancelada. O histórico de pagamentos e presenças continua guardado.');
    }
}
