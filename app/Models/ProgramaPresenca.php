<?php

/*
 * PRESENÇA NUMA AULA REGULAR (#80): quem estava (presente true/false) na chamada.
 * A frequência do mês vai para o dossiê do aluno.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $aula_id
 * @property int $assinatura_id
 * @property bool $presente
 */
class ProgramaPresenca extends Model
{
    protected $table = 'programa_presencas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['presente' => 'boolean'];
    }

    /**
     * A aula.
     *
     * @return BelongsTo<ProgramaAula, $this>
     */
    public function aula(): BelongsTo
    {
        return $this->belongsTo(ProgramaAula::class, 'aula_id');
    }

    /**
     * A assinatura (quem estava).
     *
     * @return BelongsTo<Assinatura, $this>
     */
    public function assinatura(): BelongsTo
    {
        return $this->belongsTo(Assinatura::class);
    }
}
