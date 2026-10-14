<?php

/*
 * Erro de REGRA dos cursos (#39 / #40): quando alguém tenta algo que a regra
 * não deixa (ex.: chancelar sem permissão, rejeitar sem motivo). Mesmo formato
 * do RegraFinanceira: um código estável e uma mensagem em português.
 */

namespace App\Exceptions;

use DomainException;

class RegraCurso extends DomainException
{
    /**
     * @param  string  $codigo  Código estável. Ex.: 'CURSO_ACAO_NAO_PERMITIDA'
     * @param  string  $mensagem  Texto para a pessoa. Ex.: 'Explique o motivo.'
     */
    public function __construct(public readonly string $codigo, string $mensagem)
    {
        parent::__construct($mensagem);
    }
}
