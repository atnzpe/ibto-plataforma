<?php

/*
 * CUPOM DE DESCONTO (#89). Como o cupom de um PDV: um código, um desconto
 * (percentual ou valor fixo), uma validade e limites de uso. Cupom usado
 * nunca é apagado, só desativado.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $codigo Ex.: 'FILIADO10' (maiúsculas, sem repetir).
 * @property string $tipo 'percentual' ou 'valor'.
 * @property int $valor Percentual (ex.: 10) ou centavos de desconto (ex.: 5000 = R$ 50,00).
 * @property Carbon|null $valido_de
 * @property Carbon|null $valido_ate
 * @property int|null $limite_total Usos no total; null = sem limite.
 * @property int $limite_por_pessoa Usos por pessoa (padrão 1).
 * @property bool $so_filiados
 * @property bool $todos_cursos
 * @property bool $ativo
 * @property int|null $criado_por
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['codigo', 'tipo', 'valor', 'valido_de', 'valido_ate', 'limite_total', 'limite_por_pessoa', 'so_filiados', 'todos_cursos', 'ativo'])]
class Cupom extends Model
{
    // Nome da tabela (sem isto, o Laravel tentaria "cupoms").
    protected $table = 'cupons';

    /**
     * Tipo de cada coluna especial.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'integer',
            'valido_de' => 'datetime',
            'valido_ate' => 'datetime',
            'limite_total' => 'integer',
            'limite_por_pessoa' => 'integer',
            'so_filiados' => 'boolean',
            'todos_cursos' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Quanto este cupom desconta de um preço, sem passar do preço (nunca fica negativo).
     *
     * @param  int  $precoCentavos  O preço cheio. Ex.: 25000 (R$ 250,00).
     * @return int O desconto em centavos. Ex.: 10% de 25000 → 2500.
     */
    public function descontoSobre(int $precoCentavos): int
    {
        // Percentual: arredonda para baixo (o centavo que sobra fica no preço, nunca a favor de erro).
        $desconto = $this->tipo === 'percentual'
            ? intdiv($precoCentavos * min($this->valor, 100), 100)
            : $this->valor;

        return min($desconto, $precoCentavos);
    }

    /**
     * Situação para a lista: ativo, desativado, vencido, ainda não começou ou esgotado.
     *
     * @param  int  $usos  Quantas matrículas já usaram (contadas fora, numa consulta só).
     * @return string Ex.: 'Ativo'.
     */
    public function situacao(int $usos): string
    {
        return match (true) {
            ! $this->ativo => 'Desativado',
            $this->valido_ate !== null && $this->valido_ate->isPast() => 'Vencido',
            $this->valido_de !== null && $this->valido_de->isFuture() => 'Ainda não começou',
            $this->limite_total !== null && $usos >= $this->limite_total => 'Esgotado',
            default => 'Ativo',
        };
    }

    /**
     * Texto do desconto. Ex.: '10%' ou 'R$ 50,00'.
     */
    public function rotuloDesconto(): string
    {
        return $this->tipo === 'percentual'
            ? $this->valor.'%'
            : 'R$ '.number_format($this->valor / 100, 2, ',', '.');
    }

    /**
     * Os cursos em que o cupom vale (quando não vale em todos).
     *
     * @return BelongsToMany<Curso, $this>
     */
    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'cupom_curso');
    }

    /**
     * As matrículas que usaram o cupom.
     *
     * @return HasMany<CursoMatricula, $this>
     */
    public function matriculas(): HasMany
    {
        return $this->hasMany(CursoMatricula::class);
    }
}
