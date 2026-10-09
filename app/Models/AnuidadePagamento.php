<?php

/*
 * PAGAMENTO da anuidade de um filiado num ano (#37): a "baixa" feita à mão pelo
 * administrador (data e forma), até existir o pagamento online (#42).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $filiado_id
 * @property int $ano
 * @property int $valor_centavos
 * @property Carbon $pago_em
 * @property string $forma
 * @property int|null $registrado_por
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['filiado_id', 'ano', 'valor_centavos', 'pago_em', 'forma', 'registrado_por'])]
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
}
