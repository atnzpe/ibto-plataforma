<?php

/*
 * Área de atuação do IBTO (ex.: Krav Maga, Tiro Tático), #37.
 *
 * É um cadastro de apoio, como o "grupo de produto" num PDV: equipes e
 * filiados escolhem as suas áreas a partir desta lista.
 */

namespace App\Models;

use Database\Factories\AreaAtuacaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nome
 * @property bool $ativa
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
// Campos que podem ser preenchidos de uma vez pelo formulário (o resto, como o id, não).
#[Fillable(['nome', 'ativa'])]
class AreaAtuacao extends Model
{
    /** @use HasFactory<AreaAtuacaoFactory> */
    use HasFactory;

    // Nome da tabela no banco. Sem isto, o Laravel tentaria "area_atuacaos"
    // (ele monta o plural em inglês, só acrescentando "s").
    protected $table = 'areas_atuacao';

    /**
     * Diz ao Laravel o tipo de cada coluna especial.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // O banco guarda 0 ou 1; no PHP vira false ou true.
            'ativa' => 'boolean',
        ];
    }

    /**
     * Filtro "só as ativas", para as listas de escolha (ex.: no cadastro de equipe).
     * Uso: AreaAtuacao::ativas()->get()
     *
     * @param  Builder<AreaAtuacao>  $consulta  A consulta que está sendo montada.
     * @return Builder<AreaAtuacao> A mesma consulta, agora só com as áreas ativas, em ordem de nome.
     */
    public function scopeAtivas(Builder $consulta): Builder
    {
        // Só as linhas com ativa = true, em ordem alfabética de nome.
        return $consulta->where('ativa', true)->orderBy('nome');
    }
}
