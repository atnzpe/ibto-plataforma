<?php

/*
 * Situação de um curso (#39): RASCUNHO (só o Comando Geral vê), PUBLICADO
 * (aparece no catálogo) ou ENCERRADO (sai do catálogo; histórico e
 * certificados continuam).
 */

namespace App\Enums;

enum SituacaoCurso: string
{
    // O texto à direita é o que fica gravado no banco.
    case Rascunho = 'rascunho';
    case Publicado = 'publicado';
    case Encerrado = 'encerrado';

    /**
     * Nome para mostrar na tela.
     *
     * @return string Ex.: "Publicado".
     */
    public function rotulo(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Publicado => 'Publicado',
            self::Encerrado => 'Encerrado',
        };
    }
}
