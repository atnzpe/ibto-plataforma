<?php

/*
 * AULA DE UMA TURMA (#80): um dia de aula, com a chamada. Ex.: turma "Noite" em 21/10/2026.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $turma_id
 * @property Carbon $data
 * @property Carbon|null $chamada_feita_em
 * @property-read ProgramaTurma $turma
 */
class ProgramaAula extends Model
{
    protected $table = 'programa_aulas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['data' => 'date', 'chamada_feita_em' => 'datetime'];
    }

    /**
     * A turma.
     *
     * @return BelongsTo<ProgramaTurma, $this>
     */
    public function turma(): BelongsTo
    {
        return $this->belongsTo(ProgramaTurma::class, 'turma_id');
    }

    /**
     * As presenças da chamada.
     *
     * @return HasMany<ProgramaPresenca, $this>
     */
    public function presencas(): HasMany
    {
        return $this->hasMany(ProgramaPresenca::class, 'aula_id');
    }
}
