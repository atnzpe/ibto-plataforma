<?php

/*
 * MENSALIDADE DE UMA ASSINATURA (#80): um mês (referência AAAA-MM), com o valor,
 * o vencimento e a situação (aguardando, pago ou cancelado). Pago = virou receita
 * no livro-caixa (e o repasse ao instrutor, conta a pagar).
 */

namespace App\Models;

use App\Models\Concerns\TemCodigoDePagamento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assinatura_id
 * @property string $referencia Ex.: '2026-11'.
 * @property int $valor_centavos
 * @property string $situacao 'aguardando', 'pago' ou 'cancelado'.
 * @property Carbon $vence_em
 * @property Carbon|null $pago_em
 * @property int|null $lancamento_id
 * @property int|null $confirmado_por
 * @property-read Assinatura $assinatura
 * @property string|null $codigo_pagamento Código único da página /pagar/<código> (17/10/2026).
 */
class AssinaturaPagamento extends Model
{
    // Código único da cobrança e o link /pagar/<código> (17/10/2026).
    use TemCodigoDePagamento;

    protected $table = 'assinatura_pagamentos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['vence_em' => 'date', 'pago_em' => 'datetime', 'valor_centavos' => 'integer'];
    }

    /**
     * A assinatura desta mensalidade.
     *
     * @return BelongsTo<Assinatura, $this>
     */
    public function assinatura(): BelongsTo
    {
        return $this->belongsTo(Assinatura::class);
    }
}
