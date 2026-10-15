<?php

/*
 * Categoria de um post do BLOG (#61; pedido do Mestre Eduardo em 14/10/2026:
 * "um departamento de eventos no site"): NOTÍCIAS (o que aconteceu, avisos)
 * ou EVENTOS (cobertura de um curso ou evento, com fotos e vídeos).
 */

namespace App\Enums;

enum CategoriaPost: string
{
    // O texto à direita é o que fica gravado no banco.
    case Noticia = 'noticia';
    case Evento = 'evento';

    /**
     * Nome para mostrar na tela.
     *
     * @return string Ex.: "Eventos".
     */
    public function rotulo(): string
    {
        return match ($this) {
            self::Noticia => 'Notícias',
            self::Evento => 'Eventos',
        };
    }
}
