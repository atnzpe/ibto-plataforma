<?php

/*
 * TRILHA de formação (#39, esboço do Gleyson de 08/10/2026): um conjunto de
 * cursos EM ORDEM. Ex.: do Praticante ao Instrutor Expert.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nome
 * @property string|null $descricao
 * @property bool $ativa
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nome', 'descricao', 'ativa'])]
class Trilha extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ativa' => 'boolean'];
    }

    /**
     * Os cursos da trilha, já na ordem (1ª etapa primeiro).
     *
     * @return BelongsToMany<Curso, $this>
     */
    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'curso_trilha')->withPivot('ordem')->orderBy('curso_trilha.ordem');
    }
}
