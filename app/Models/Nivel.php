<?php

/*
 * Nível do IBTO (ex.: "Instrutor Expert IV"), #37.
 *
 * Cadastro de apoio, como a "graduação" numa academia de arte marcial: o
 * filiado recebe um destes níveis. O nome é montado da categoria + grau.
 */

namespace App\Models;

use App\Enums\CategoriaNivel;
use Database\Factories\NivelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property CategoriaNivel $categoria
 * @property int|null $grau
 * @property bool $ativo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
// Campos que o formulário pode preencher de uma vez.
#[Fillable(['categoria', 'grau', 'ativo'])]
class Nivel extends Model
{
    /** @use HasFactory<NivelFactory> */
    use HasFactory;

    // Nome da tabela. Sem isto, o Laravel tentaria "nivels" (plural em inglês).
    protected $table = 'niveis';

    // Graus de 1 a 5 escritos em algarismos romanos, como nas graduações.
    private const ROMANOS = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V'];

    /**
     * Tipo de cada coluna especial.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // O texto do banco vira uma das opções do enum (ex.: CategoriaNivel::Monitor).
            'categoria' => CategoriaNivel::class,
            'grau' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Nome do nível para as telas.
     *
     * @return string Ex.: "Instrutor Expert IV"; "Praticante" (sem grau).
     */
    public function nome(): string
    {
        // Sem grau: só a categoria.
        if ($this->grau === null) {
            return $this->categoria->value;
        }

        return $this->categoria->value.' '.self::ROMANOS[$this->grau];
    }

    /**
     * Filtro "só os ativos", para as listas de escolha (ex.: no cadastro de filiado).
     * Uso: Nivel::ativos()->get()
     *
     * @param  Builder<Nivel>  $consulta  A consulta que está sendo montada.
     * @return Builder<Nivel> A mesma consulta, só com os níveis ativos.
     */
    public function scopeAtivos(Builder $consulta): Builder
    {
        return $consulta->where('ativo', true);
    }

    /**
     * Põe os níveis na ordem da hierarquia: categoria (Praticante → Instrutor
     * Expert) e, dentro dela, o grau (I → V). Ordem alfabética não serviria:
     * "Instrutor" viria antes de "Monitor".
     *
     * @param  Collection<int, Nivel>  $niveis  Os níveis, em qualquer ordem.
     * @return Collection<int, Nivel> Os mesmos níveis, na ordem da hierarquia.
     */
    public static function emOrdem(Collection $niveis): Collection
    {
        return $niveis->sortBy([
            fn (Nivel $a, Nivel $b) => $a->categoria->posicao() <=> $b->categoria->posicao(),
            fn (Nivel $a, Nivel $b) => ($a->grau ?? 0) <=> ($b->grau ?? 0),
        ])->values();
    }
}
