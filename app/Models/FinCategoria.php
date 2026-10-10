<?php

/*
 * CATEGORIA do financeiro (#59): para que é o dinheiro. Ex.: 'Anuidade' (receita),
 * 'Taxas bancárias' (despesa).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nome
 * @property string $tipo
 * @property bool $ativa
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nome', 'tipo', 'ativa'])]
class FinCategoria extends Model
{
    protected $table = 'fin_categorias';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ativa' => 'boolean'];
    }

    /**
     * Lançamentos que usam esta categoria.
     *
     * @return HasMany<FinLancamento, $this>
     */
    public function lancamentos(): HasMany
    {
        return $this->hasMany(FinLancamento::class, 'categoria_id');
    }
}
