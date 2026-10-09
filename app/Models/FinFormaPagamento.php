<?php

/*
 * FORMA DE PAGAMENTO do financeiro (#59): como o dinheiro entrou ou saiu.
 * Ex.: 'PIX', 'Dinheiro', 'Transferência', 'Cartão'.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nome
 * @property bool $ativa
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nome', 'ativa'])]
class FinFormaPagamento extends Model
{
    protected $table = 'fin_formas_pagamento';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ativa' => 'boolean'];
    }
}
