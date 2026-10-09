<?php

/*
 * Valor da ANUIDADE de um ano (#37). Ex.: 2027 = R$ 90,00; 2026 = isento.
 * O valor fica em centavos (9000 = R$ 90,00) para não errar contas de dinheiro.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ano
 * @property int $valor_centavos
 * @property bool $isenta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['ano', 'valor_centavos', 'isenta'])]
class Anuidade extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Números do banco viram números no PHP (e 0/1 vira false/true).
            'ano' => 'integer',
            'valor_centavos' => 'integer',
            'isenta' => 'boolean',
        ];
    }
}
