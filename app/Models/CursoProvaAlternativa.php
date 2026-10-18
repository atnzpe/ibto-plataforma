<?php

/*
 * ALTERNATIVA DE UMA PERGUNTA (#43). "correta" é o GABARITO: nunca vai para a
 * tela do aluno (a tela recebe só o número e o texto).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $questao_id
 * @property string $texto
 * @property bool $correta
 * @property int $ordem
 */
class CursoProvaAlternativa extends Model
{
    protected $table = 'curso_prova_alternativas';

    /**
     * Os campos que o formulário do instrutor preenche.
     *
     * @var list<string>
     */
    protected $fillable = ['texto', 'correta', 'ordem'];

    /**
     * Nunca sai em JSON: se alguém mandar o modelo inteiro para a tela por
     * engano, o gabarito não vai junto.
     *
     * @var list<string>
     */
    protected $hidden = ['correta'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['correta' => 'boolean'];
    }
}
