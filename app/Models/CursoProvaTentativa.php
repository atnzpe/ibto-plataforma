<?php

/*
 * TENTATIVA DE PROVA (#43): cada vez que o aluno respondeu, com a nota (0 a
 * 100), se aprovou e as respostas escolhidas ({número da pergunta: número da alternativa}).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $prova_id
 * @property int $matricula_id
 * @property int $nota
 * @property bool $aprovada
 * @property array<string, int> $respostas
 * @property Carbon|null $created_at
 */
class CursoProvaTentativa extends Model
{
    protected $table = 'curso_prova_tentativas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['nota' => 'integer', 'aprovada' => 'boolean', 'respostas' => 'array'];
    }

    /**
     * A prova.
     *
     * @return BelongsTo<CursoProva, $this>
     */
    public function prova(): BelongsTo
    {
        return $this->belongsTo(CursoProva::class, 'prova_id');
    }
}
