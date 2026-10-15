<?php

/*
 * Situação de um post do BLOG (#61, no molde das notícias do site da CBSA):
 * RASCUNHO → EM REVISÃO → PUBLICADO → ARQUIVADO. "Agendado" não é uma
 * situação à parte: é um post PUBLICADO com a data de publicação no futuro
 * (ele aparece sozinho quando a data chega). Só o publicado aparece no site.
 * As regras de cada passo ficam em App\Actions\Blog\PublicacaoPost.
 */

namespace App\Enums;

enum SituacaoPost: string
{
    // O texto à direita é o que fica gravado no banco.
    case Rascunho = 'rascunho';
    case EmRevisao = 'em_revisao';
    case Publicado = 'publicado';
    case Arquivado = 'arquivado';

    /**
     * Nome para mostrar na tela.
     *
     * @return string Ex.: "Em revisão".
     */
    public function rotulo(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::EmRevisao => 'Em revisão',
            self::Publicado => 'Publicado',
            self::Arquivado => 'Arquivado',
        };
    }
}
