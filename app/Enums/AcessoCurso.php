<?php

/*
 * Quem pode se inscrever num curso (#38): ABERTO (qualquer pessoa) ou RESTRITO
 * (só filiados ativos). Junto com o valor, forma as três modalidades do IBTO:
 * gratuito e restrito; pago e aberto; pago e restrito.
 */

namespace App\Enums;

enum AcessoCurso: string
{
    // O texto à direita é o que fica gravado no banco.
    case Aberto = 'aberto';
    case Restrito = 'restrito';

    /**
     * Nome para mostrar na tela.
     *
     * @return string Ex.: "Só filiados".
     */
    public function rotulo(): string
    {
        return match ($this) {
            self::Aberto => 'Aberto ao público',
            self::Restrito => 'Só filiados',
        };
    }
}
