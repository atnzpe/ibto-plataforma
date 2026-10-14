<?php

/*
 * Situação de uma MATRÍCULA (#41). Curso gratuito: CONFIRMADA na hora. Curso
 * pago: AGUARDANDO PAGAMENTO até o IBTO confirmar o PIX. CANCELADA não apaga
 * nada: a vaga volta e o histórico fica.
 */

namespace App\Enums;

enum SituacaoMatricula: string
{
    // O texto à direita é o que fica gravado no banco.
    case AguardandoPagamento = 'aguardando_pagamento';
    case Confirmada = 'confirmada';
    case Cancelada = 'cancelada';

    /**
     * Nome para mostrar na tela.
     *
     * @return string Ex.: "Aguardando pagamento".
     */
    public function rotulo(): string
    {
        return match ($this) {
            self::AguardandoPagamento => 'Aguardando pagamento',
            self::Confirmada => 'Confirmada',
            self::Cancelada => 'Cancelada',
        };
    }
}
