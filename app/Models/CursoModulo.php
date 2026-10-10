<?php

/*
 * MÓDULO de um curso (#39): um grupo de aulas, em ordem. Pode exigir um NÍVEL
 * MÍNIMO (mesma lógica do Dojo Manager, pedido do Gleyson em 10/10/2026): o
 * filiado vê os módulos do seu nível e do SEGUINTE; quem cuida dos cursos vê tudo.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $curso_id
 * @property string $titulo
 * @property int $ordem
 * @property int|null $nivel_minimo_id
 * @property bool $ativo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['curso_id', 'titulo', 'ordem', 'nivel_minimo_id', 'ativo'])]
class CursoModulo extends Model
{
    protected $table = 'curso_modulos';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ordem' => 'integer', 'ativo' => 'boolean'];
    }

    /**
     * O módulo está LIBERADO para alguém deste nível?
     * Regra do Dojo Manager: liberado se não exige nível, ou se a posição do
     * nível exigido é no máximo a do aluno + 1 (o nível dele e o seguinte).
     *
     * @param  int|null  $nivelDoAlunoId  O nível do filiado (null = sem nível, ex.: aluno do público).
     * @param  array<int, int>  $posicoes  id do nível => posição (Nivel::posicoes()).
     * @return bool true = pode ver.
     */
    public function liberadoPara(?int $nivelDoAlunoId, array $posicoes): bool
    {
        // Módulo sem nível mínimo: todos os inscritos veem.
        if ($this->nivel_minimo_id === null) {
            return true;
        }

        // Posição exigida e a do aluno (sem nível = 0: só vê o 1º nível, o "seguinte" do zero).
        $exigida = $posicoes[$this->nivel_minimo_id] ?? PHP_INT_MAX;
        $doAluno = $nivelDoAlunoId !== null ? ($posicoes[$nivelDoAlunoId] ?? 0) : 0;

        return $exigida <= $doAluno + 1;
    }

    /**
     * @return BelongsTo<Curso, $this>
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * @return BelongsTo<Nivel, $this>
     */
    public function nivelMinimo(): BelongsTo
    {
        return $this->belongsTo(Nivel::class, 'nivel_minimo_id');
    }

    /**
     * As aulas do módulo, já na ordem.
     *
     * @return HasMany<CursoAula, $this>
     */
    public function aulas(): HasMany
    {
        return $this->hasMany(CursoAula::class, 'modulo_id')->orderBy('ordem')->orderBy('id');
    }
}
