<?php

/*
 * PAGAMENTO da anuidade de um filiado num ano (#37): a "baixa" feita à mão pelo
 * administrador, até existir o pagamento online (#42). Cada pagamento é ligado
 * à RECEITA que ele gerou no livro-caixa (#59): a forma de pagamento e um
 * eventual estorno ficam lá. Quem cria é o App\Services\Anuidades.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $filiado_id
 * @property int $ano
 * @property int $valor_centavos
 * @property Carbon $pago_em
 * @property int $lancamento_id
 * @property int|null $registrado_por
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['filiado_id', 'ano', 'valor_centavos', 'pago_em', 'lancamento_id', 'registrado_por'])]
class AnuidadePagamento extends Model
{
    // Nome da tabela, escrito à mão para ficar claro (o Laravel adivinharia o mesmo).
    protected $table = 'anuidade_pagamentos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'valor_centavos' => 'integer',
            // Texto "2027-03-15" do banco vira uma data no PHP.
            'pago_em' => 'date',
        ];
    }

    /**
     * De quem é o pagamento.
     *
     * @return BelongsTo<Filiado, $this>
     */
    public function filiado(): BelongsTo
    {
        return $this->belongsTo(Filiado::class);
    }

    /**
     * A receita no livro-caixa que este pagamento gerou.
     *
     * @return BelongsTo<FinLancamento, $this>
     */
    public function lancamento(): BelongsTo
    {
        return $this->belongsTo(FinLancamento::class, 'lancamento_id');
    }

    /**
     * Só os pagamentos VÁLIDOS: a receita ligada não foi estornada.
     * Uso: $filiado->pagamentosAnuidade()->validos()->where('ano', 2027)->exists()
     *
     * @param  Builder<AnuidadePagamento>  $consulta  A consulta que vai ser filtrada (o Laravel passa sozinho).
     * @return Builder<AnuidadePagamento> A mesma consulta, só com os válidos.
     */
    public function scopeValidos(Builder $consulta): Builder
    {
        // whereDoesntHave: só os pagamentos cuja receita NÃO tem estorno.
        return $consulta->whereDoesntHave('lancamento.estorno');
    }
}
