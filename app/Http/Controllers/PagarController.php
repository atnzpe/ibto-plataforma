<?php

/*
 * PÁGINA DE PAGAMENTO ÚNICA /pagar/<código> (pedido do Gleyson em 17/10/2026).
 *
 * Uma página só, no estilo "checkout", para qualquer cobrança: inscrição em
 * curso, pedido de fotos ou mensalidade de aula regular. Mostra o resumo, o
 * valor, o vencimento, o Pix (QR + copia e cola + identificador do extrato) e a
 * situação, que se atualiza sozinha. O código é sorteado e nunca se repete
 * (ULID): ninguém chega ao pagamento de outra pessoa trocando um número.
 * Quem abre: só o DONO da cobrança, ou o Comando Geral (pagamentos.confirmar);
 * os outros recebem "não encontrado" (404), sem saber se o código existe.
 */

namespace App\Http\Controllers;

use App\Enums\SituacaoMatricula;
use App\Http\Controllers\AulasRegulares\MensalidadeController;
use App\Http\Controllers\Fotos\AlbunsVendaController;
use App\Models\AssinaturaPagamento;
use App\Models\CursoMatricula;
use App\Models\PedidoFoto;
use App\Models\User;
use App\Support\DadosPagamento;
use App\Support\PixCopiaECola;
use App\Support\QrCode;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PagarController extends Controller
{
    /**
     * A página de pagamento.
     *
     * @param  Request  $request  O pedido de quem está logado.
     * @param  string  $codigo  O código único. Ex.: '01jb8x0k3m...'.
     * @return Response A tela "pagar".
     */
    public function show(Request $request, string $codigo): Response
    {
        $codigo = strtolower(trim($codigo));
        // Só letras e números de ULID (26): qualquer outra coisa nem consulta o banco.
        abort_unless(preg_match('/^[0-9a-z]{26}$/', $codigo) === 1, 404);

        $cobranca = $this->matricula($codigo) ?? $this->pedidoFotos($codigo) ?? $this->mensalidade($codigo);
        // Não existe, ou não é desta pessoa (e ela não é do Comando Geral): "não encontrado".
        abort_if($cobranca === null || ! $this->podeVer($request->user(), $cobranca['dono_id']), 404);
        unset($cobranca['dono_id']);

        return Inertia::render('pagar', ['cobranca' => $cobranca]);
    }

    /**
     * Pode ver: o dono da cobrança ou quem confirma pagamentos.
     */
    private function podeVer(User $usuario, int $donoId): bool
    {
        return $usuario->id === $donoId || $usuario->can('pagamentos.confirmar');
    }

    /**
     * A cobrança de uma inscrição em curso, ou null.
     *
     * @return array<string, mixed>|null
     */
    private function matricula(string $codigo): ?array
    {
        $m = CursoMatricula::query()->with('curso')->where('codigo_pagamento', $codigo)->first();
        if ($m === null) {
            return null;
        }
        $aguardando = $m->situacao === SituacaoMatricula::AguardandoPagamento;
        $situacao = match ($m->situacao) {
            SituacaoMatricula::Confirmada => 'pago',
            SituacaoMatricula::Cancelada => 'cancelado',
            default => 'aguardando',
        };

        return [
            'dono_id' => $m->user_id,
            'tipo' => 'Inscrição em curso',
            'descricao' => $m->curso->titulo,
            'valor_centavos' => $m->valor_centavos,
            'vence_em' => $m->curso->data_inicio?->format('d/m/Y'),
            'situacao' => $situacao,
            'pix' => $aguardando ? $this->pix(DadosPagamento::doCurso($m->curso), $m->valor_centavos, PixCopiaECola::txidDaMatricula($m->id)) : null,
            'voltar' => ['rotulo' => 'Meus cursos', 'href' => route('meus-cursos')],
        ];
    }

    /**
     * A cobrança de um pedido de fotos, ou null.
     *
     * @return array<string, mixed>|null
     */
    private function pedidoFotos(string $codigo): ?array
    {
        $p = PedidoFoto::query()->with('album')->where('codigo_pagamento', $codigo)->first();
        if ($p === null) {
            return null;
        }
        $situacao = match ($p->situacao) {
            'pago' => 'pago',
            'cancelado' => 'cancelado',
            default => 'aguardando',
        };

        return [
            'dono_id' => $p->user_id,
            'tipo' => 'Fotos do evento',
            'descricao' => $p->album->titulo.' ('.$p->fotos()->count().' fotos)',
            'valor_centavos' => $p->valor_centavos,
            'vence_em' => null,
            'situacao' => $situacao,
            'pix' => $situacao === 'aguardando' ? $this->pix(DadosPagamento::doIbto(), $p->valor_centavos, AlbunsVendaController::txid($p->id)) : null,
            'voltar' => ['rotulo' => 'Ver o pedido e baixar', 'href' => route('fotos.pedido', $p)],
        ];
    }

    /**
     * A cobrança de uma mensalidade de aula regular, ou null.
     *
     * @return array<string, mixed>|null
     */
    private function mensalidade(string $codigo): ?array
    {
        $m = AssinaturaPagamento::query()->with('assinatura.programa')->where('codigo_pagamento', $codigo)->first();
        if ($m === null) {
            return null;
        }

        return [
            'dono_id' => $m->assinatura->user_id,
            'tipo' => 'Mensalidade de aula regular',
            'descricao' => $m->assinatura->programa->titulo.' — '.$m->referencia,
            'valor_centavos' => $m->valor_centavos,
            'vence_em' => $m->vence_em->format('d/m/Y'),
            'situacao' => $m->situacao,
            'pix' => $m->situacao === 'aguardando' ? $this->pix(DadosPagamento::doIbto(), $m->valor_centavos, MensalidadeController::txid($m->id)) : null,
            'voltar' => ['rotulo' => 'Minhas aulas', 'href' => route('aulas.minhas')],
        ];
    }

    /**
     * O Pix copia e cola (QR e identificador), ou null sem chave Pix configurada.
     *
     * @param  array{pix_chave: string, favorecido: string}  $dados  Os dados de quem recebe.
     * @return array<string, string>|null
     */
    private function pix(array $dados, int $valorCentavos, string $txid): ?array
    {
        // Sem chave Pix: a página avisa que o IBTO precisa configurar.
        if ($dados['pix_chave'] === '') {
            return null;
        }
        $favorecido = $dados['favorecido'] !== '' ? $dados['favorecido'] : 'IBTO';
        $codigo = PixCopiaECola::gerar($dados['pix_chave'], $favorecido, (string) config('app.pix_cidade'), $valorCentavos, $txid);

        return ['copia_e_cola' => $codigo, 'qr_svg' => QrCode::svg($codigo, 240), 'txid' => $txid, 'favorecido' => $favorecido, 'chave' => $dados['pix_chave']];
    }
}
