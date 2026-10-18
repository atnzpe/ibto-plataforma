<?php

/*
 * TURMA DE UM PROGRAMA (#80): os dias e o horário das aulas, presencial (local)
 * ou online (link da sala). Ex.: "Noite", ter e qui, 19h às 20h30.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $programa_id
 * @property string $nome
 * @property string $dias_semana Ex.: 'ter,qui'.
 * @property string $hora_inicio Ex.: '19:00:00'.
 * @property string|null $hora_fim
 * @property string|null $local
 * @property string|null $link_sala
 * @property bool $ativa
 * @property-read Programa $programa
 */
#[Fillable(['nome', 'dias_semana', 'hora_inicio', 'hora_fim', 'local', 'link_sala', 'ativa'])]
class ProgramaTurma extends Model
{
    /** Os dias da semana aceitos, na ordem, com o rótulo. */
    public const DIAS = ['seg' => 'Segunda', 'ter' => 'Terça', 'qua' => 'Quarta', 'qui' => 'Quinta', 'sex' => 'Sexta', 'sab' => 'Sábado', 'dom' => 'Domingo'];

    protected $table = 'programa_turmas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ativa' => 'boolean'];
    }

    /**
     * Os dias e o horário escritos por extenso. Ex.: 'Terça e Quinta, 19:00–20:30'.
     */
    public function horarioPorExtenso(): string
    {
        $dias = array_values(array_filter(array_map(fn (string $d) => self::DIAS[trim($d)] ?? null, explode(',', $this->dias_semana))));
        $texto = count($dias) > 1 ? implode(', ', array_slice($dias, 0, -1)).' e '.end($dias) : ($dias[0] ?? '');

        return $texto.', '.substr($this->hora_inicio, 0, 5).($this->hora_fim ? '–'.substr($this->hora_fim, 0, 5) : '');
    }

    /**
     * O programa da turma.
     *
     * @return BelongsTo<Programa, $this>
     */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class);
    }

    /**
     * As aulas já registradas (com chamada).
     *
     * @return HasMany<ProgramaAula, $this>
     */
    public function aulas(): HasMany
    {
        return $this->hasMany(ProgramaAula::class, 'turma_id')->orderByDesc('data');
    }
}
