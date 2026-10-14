<?php

/*
 * Modalidade de um curso (#39): presencial (encontros com data e local) ou
 * online (aulas com vídeo, no ritmo do aluno).
 */

namespace App\Enums;

enum ModalidadeCurso: string
{
    // O texto à direita é o que fica gravado no banco.
    case Presencial = 'presencial';
    case Online = 'online';

    /**
     * Nome para mostrar na tela.
     *
     * @return string Ex.: "Presencial".
     */
    public function rotulo(): string
    {
        return match ($this) {
            self::Presencial => 'Presencial',
            self::Online => 'Online',
        };
    }
}
