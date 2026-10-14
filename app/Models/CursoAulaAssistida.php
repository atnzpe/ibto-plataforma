<?php

/*
 * AULA ASSISTIDA (progresso das aulas online, 14/10/2026): "esta pessoa
 * marcou esta aula como assistida". Gravada só pelo App\Services\SalaDeAula;
 * nenhum campo vem direto de formulário. Uma linha por pessoa e aula.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $aula_id
 * @property Carbon $assistida_em
 */
class CursoAulaAssistida extends Model
{
    // O nome da tabela no plural do português (o Laravel adivinharia em inglês).
    protected $table = 'curso_aulas_assistidas';

    // Sem created_at/updated_at: a única data que importa é "assistida_em".
    public $timestamps = false;

    // Nada vem de formulário: o serviço grava com forceFill.
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Texto do banco vira data e hora no PHP.
            'assistida_em' => 'datetime',
        ];
    }
}
