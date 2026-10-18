<?php

/*
 * PERGUNTA DE UMA PROVA (#43), com as alternativas em ordem.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $prova_id
 * @property string $enunciado
 * @property int $ordem
 */
class CursoProvaQuestao extends Model
{
    protected $table = 'curso_prova_questoes';

    /**
     * Os campos que o formulário do instrutor preenche.
     *
     * @var list<string>
     */
    protected $fillable = ['enunciado', 'ordem'];

    /**
     * As alternativas, em ordem.
     *
     * @return HasMany<CursoProvaAlternativa, $this>
     */
    public function alternativas(): HasMany
    {
        return $this->hasMany(CursoProvaAlternativa::class, 'questao_id')->orderBy('ordem')->orderBy('id');
    }
}
