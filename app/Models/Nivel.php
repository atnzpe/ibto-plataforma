<?php

/*
 * Nível do IBTO (ex.: "Instrutor Expert IV"), #37.
 *
 * Cadastro de apoio, como a "graduação" numa academia de arte marcial: o
 * filiado recebe um destes níveis. O nome é montado da categoria + grau, ou é
 * o nome escolhido pelo Comando Geral (níveis livres, 18/10/2026). A ordem da
 * hierarquia vem da coluna "posicao" (10, 20, 30...; o mais baixo primeiro).
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
 * @property string|null $nome_personalizado Ex.: 'Grão-Mestre'; null = nome automático.
 * @property int $posicao Ex.: 10 (Praticante), 20 (Monitor I)...
 * @property string|null $letra Ex.: 'G'; null = a letra da categoria.
 * @property bool $ativo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
// Campos que o formulário pode preencher de uma vez.
#[Fillable(['categoria', 'grau', 'nome_personalizado', 'posicao', 'letra', 'ativo'])]
class Nivel extends Model
{
    /** @use HasFactory<NivelFactory> */
    use HasFactory;

    // Nome da tabela. Sem isto, o Laravel tentaria "nivels" (plural em inglês).
    protected $table = 'niveis';

    // Graus de 1 a 5 escritos em algarismos romanos, como nas graduações.
    private const ROMANOS = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V'];

    // A letra de cada categoria no número de série do certificado de graduação.
    private const LETRAS = [
        'Praticante' => 'P',
        'Monitor' => 'M',
        'Instrutor' => 'I',
        'Instrutor Expert' => 'E',
    ];

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
            'posicao' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Nome do nível para as telas.
     *
     * @return string Ex.: "Grão-Mestre" (nome escolhido); "Instrutor Expert IV"; "Praticante" (sem grau).
     */
    public function nome(): string
    {
        // Nome escolhido pelo Comando Geral: ele vale mais que o automático.
        if ($this->nome_personalizado !== null && trim($this->nome_personalizado) !== '') {
            return $this->nome_personalizado;
        }

        // Sem grau: só a categoria.
        if ($this->grau === null) {
            return $this->categoria->value;
        }

        return $this->categoria->value.' '.self::ROMANOS[$this->grau];
    }

    /**
     * A letra do número de série (certificado de graduação): a escolhida no
     * cadastro ou, se vazia, a da categoria.
     *
     * @return string Ex.: 'E' (Instrutor Expert); 'G' (escolhida para "Grão-Mestre").
     */
    public function letraSerie(): string
    {
        // Letra escolhida no cadastro: sempre maiúscula.
        if ($this->letra !== null && trim($this->letra) !== '') {
            return strtoupper($this->letra);
        }

        // Sem letra escolhida: a da categoria (toda categoria do enum tem a sua letra).
        return self::LETRAS[$this->categoria->value];
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
     * POSIÇÃO de cada nível na hierarquia (como a faixa no Dojo Manager), para
     * a liberação do conteúdo dos cursos (#39): "o meu nível e o seguinte".
     * Ex.: [id do Praticante => 1, Monitor I => 2, Monitor II => 3, Instrutor I => 4, ...]
     *
     * @return array<int, int> id do nível => posição (1 = o mais baixo).
     */
    public static function posicoes(): array
    {
        $posicoes = [];
        foreach (self::emOrdem(self::all()) as $indice => $nivel) {
            $posicoes[$nivel->id] = $indice + 1;
        }

        return $posicoes;
    }

    /**
     * Põe os níveis na ordem da hierarquia, pela coluna "posicao" (o mais baixo
     * primeiro); empate fica com quem foi cadastrado antes. Ordem alfabética não
     * serviria: "Instrutor" viria antes de "Monitor".
     *
     * @param  Collection<int, Nivel>  $niveis  Os níveis, em qualquer ordem.
     * @return Collection<int, Nivel> Os mesmos níveis, na ordem da hierarquia.
     */
    public static function emOrdem(Collection $niveis): Collection
    {
        return $niveis->sortBy([
            fn (Nivel $a, Nivel $b) => $a->posicao <=> $b->posicao,
            fn (Nivel $a, Nivel $b) => $a->id <=> $b->id,
        ])->values();
    }

    /**
     * Renumera a ordem de todos os níveis em 10, 20, 30... Usado depois de
     * encaixar um nível no meio (ex.: o novo ganhou a posição 21, entre 20 e 30),
     * para sempre sobrar espaço para o próximo encaixe.
     */
    public static function renumerar(): void
    {
        foreach (self::emOrdem(self::all()) as $indice => $nivel) {
            $nova = ($indice + 1) * 10;
            // Só grava quem mudou (evita escrever no banco à toa).
            if ($nivel->posicao !== $nova) {
                $nivel->forceFill(['posicao' => $nova])->save();
            }
        }
    }
}
