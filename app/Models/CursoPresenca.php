<?php

/*
 * PRESENÇA de um inscrito num encontro (#44). Gravada pela chamada
 * (App\Http\Controllers\Cursos\ChamadaController); nenhum campo vem direto
 * de formulário. Uma linha por pessoa em cada encontro.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $aula_id
 * @property int $matricula_id
 * @property bool $presente
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CursoPresenca extends Model
{
    // Nada vem de formulário: a chamada grava com forceFill / updateOrCreate controlado.
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // 0/1 do banco vira false/true.
        return ['presente' => 'boolean'];
    }

    /**
     * @return BelongsTo<CursoAula, $this>
     */
    public function aula(): BelongsTo
    {
        return $this->belongsTo(CursoAula::class, 'aula_id');
    }

    /**
     * @return BelongsTo<CursoMatricula, $this>
     */
    public function matricula(): BelongsTo
    {
        return $this->belongsTo(CursoMatricula::class, 'matricula_id');
    }
}
