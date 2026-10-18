<?php

/*
 * PROGRAMA DE AULAS REGULARES (#80): a modalidade contínua com mensalidade.
 * Ex.: "Krav Maga online", R$ 120,00 por mês, turmas às terças e quintas.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $titulo
 * @property string|null $descricao
 * @property int|null $area_atuacao_id
 * @property string $modalidade 'presencial', 'online' ou 'hibrido'.
 * @property int|null $instrutor_id O instrutor responsável (faz a chamada).
 * @property string|null $instrutor_nome O nome que aparece na vitrine.
 * @property int $valor_mensal_centavos Ex.: 12000 = R$ 120,00.
 * @property int $percentual_ibto Ex.: 20 (o instrutor fica com 80%).
 * @property int|null $vagas null = sem limite.
 * @property bool $so_filiados true = só filiados assinam.
 * @property int $dia_vencimento 1 a 28.
 * @property bool $ativo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['titulo', 'descricao', 'area_atuacao_id', 'modalidade', 'instrutor_id', 'instrutor_nome', 'valor_mensal_centavos', 'percentual_ibto', 'vagas', 'so_filiados', 'dia_vencimento', 'ativo'])]
class Programa extends Model
{
    /** As modalidades possíveis e o rótulo de cada uma. */
    public const MODALIDADES = ['presencial' => 'Presencial', 'online' => 'Online (ao vivo)', 'hibrido' => 'Presencial e online'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['so_filiados' => 'boolean', 'ativo' => 'boolean', 'valor_mensal_centavos' => 'integer', 'percentual_ibto' => 'integer', 'vagas' => 'integer', 'dia_vencimento' => 'integer'];
    }

    /**
     * DIVIDE uma mensalidade entre o IBTO e o instrutor, em centavos. A parte do
     * IBTO é arredondada para baixo; o centavo que sobrar vai para o instrutor.
     *
     * @param  int  $valorCentavos  Ex.: 12000.
     * @return array{ibto: int, instrutor: int} Ex.: ['ibto' => 2400, 'instrutor' => 9600].
     */
    public function divisao(int $valorCentavos): array
    {
        $ibto = intdiv($valorCentavos * $this->percentual_ibto, 100);

        return ['ibto' => $ibto, 'instrutor' => $valorCentavos - $ibto];
    }

    /**
     * As turmas (dias e horários).
     *
     * @return HasMany<ProgramaTurma, $this>
     */
    public function turmas(): HasMany
    {
        return $this->hasMany(ProgramaTurma::class)->orderBy('nome');
    }

    /**
     * As assinaturas.
     *
     * @return HasMany<Assinatura, $this>
     */
    public function assinaturas(): HasMany
    {
        return $this->hasMany(Assinatura::class);
    }

    /**
     * O instrutor responsável.
     *
     * @return BelongsTo<User, $this>
     */
    public function instrutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instrutor_id');
    }

    /**
     * A área de atuação.
     *
     * @return BelongsTo<AreaAtuacao, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(AreaAtuacao::class, 'area_atuacao_id');
    }
}
