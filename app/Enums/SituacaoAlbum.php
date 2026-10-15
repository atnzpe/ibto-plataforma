<?php

/*
 * Situação de um ÁLBUM da galeria de eventos (#61, bloco B2): RASCUNHO
 * (montando: enviando fotos e descrevendo) → PUBLICADO (aparece no site) →
 * ARQUIVADO (sai do site sem apagar). As regras de cada passo ficam em
 * App\Actions\Blog\PublicacaoAlbum.
 */

namespace App\Enums;

enum SituacaoAlbum: string
{
    // O texto à direita é o que fica gravado no banco.
    case Rascunho = 'rascunho';
    case Publicado = 'publicado';
    case Arquivado = 'arquivado';

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
            self::Arquivado => 'Arquivado',
        };
    }
}
