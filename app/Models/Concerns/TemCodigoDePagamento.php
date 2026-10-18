<?php

/*
 * CÓDIGO DE PAGAMENTO (17/10/2026): peça compartilhada pelas cobranças
 * (matrícula, pedido de fotos, mensalidade). Ao criar, sorteia um código que
 * nunca se repete (ULID) e oferece o link da página de pagamento /pagar/<código>.
 * Um "trait" é um pedaço de código que várias classes reaproveitam.
 */

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait TemCodigoDePagamento
{
    /**
     * O Laravel chama sozinho: ao criar a cobrança, sorteia o código (se ainda não tiver).
     */
    public static function bootTemCodigoDePagamento(): void
    {
        static::creating(function ($cobranca): void {
            $cobranca->codigo_pagamento ??= strtolower((string) Str::ulid());
        });
    }

    /**
     * O endereço da página de pagamento. Ex.: 'https://cursos.ib-to.org/pagar/01jb8x...'.
     */
    public function linkDePagamento(): string
    {
        return route('pagar.show', $this->codigo_pagamento);
    }
}
