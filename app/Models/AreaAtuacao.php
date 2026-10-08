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
 * @property string|null $imagem
 * @property bool $ativa
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
// Campos que podem ser preenchidos de uma vez pelo formulário (o resto, como o id, não).
// "imagem" fica de fora de propósito: quem grava o caminho é o controller, depois
// de guardar o arquivo; assim ninguém consegue mandar um caminho inventado.
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
     * Endereço público da imagem da área, para as telas e o site (#57).
     * O "?v=" muda quando a área é alterada: assim o navegador não mostra a
     * imagem antiga guardada na memória dele depois de uma troca.
     *
     * @return string|null Ex.: 'https://cursos.ib-to.org/imagens/areas-atuacao/4?v=1759870000'; null se não tem imagem.
     */
    public function imagemUrl(): ?string
    {
        // Sem imagem cadastrada: não há endereço.
        if ($this->imagem === null) {
            return null;
        }

        return route('areas-atuacao.imagem', [
            'area' => $this->id,
            'v' => $this->updated_at?->getTimestamp(),
        ]);
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
