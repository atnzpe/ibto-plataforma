<?php

/*
 * Erro de REGRA do blog (#61): quando alguém tenta algo que a regra não deixa
 * (ex.: publicar sem capa, ou com fotos de pessoas sem a autorização de
 * imagem). Mesmo formato do RegraCurso: um código estável e uma mensagem em
 * português.
 */

namespace App\Exceptions;

use DomainException;

class RegraBlog extends DomainException
{
    /**
     * @param  string  $codigo  Código estável. Ex.: 'POST_SEM_AUTORIZACAO_IMAGEM'
     * @param  string  $mensagem  Texto para a pessoa. Ex.: 'Marque a autorização de uso de imagem.'
     */
    public function __construct(public readonly string $codigo, string $mensagem)
    {
        parent::__construct($mensagem);
    }
}
