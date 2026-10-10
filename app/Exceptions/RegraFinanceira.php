<?php

/*
 * Erro de REGRA do financeiro (#59): quando alguém tenta algo que a regra não
 * deixa (ex.: alterar um lançamento pago). Leva um código estável, como os
 * outros erros públicos da plataforma, e uma mensagem em português para a tela.
 */

namespace App\Exceptions;

use DomainException;

class RegraFinanceira extends DomainException
{
    /**
     * @param  string  $codigo  Código estável. Ex.: 'LANCAMENTO_PAGO_IMUTAVEL'
     * @param  string  $mensagem  Texto para a pessoa. Ex.: 'Lançamento pago não pode ser alterado.'
     */
    public function __construct(public readonly string $codigo, string $mensagem)
    {
        parent::__construct($mensagem);
    }
}
