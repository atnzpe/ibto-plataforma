<?php

/*
 * Situação de uma equipe filiada (#37, Gleyson 07/10/2026).
 *
 * - Em homologação: cadastro em análise pelo IBTO; pode estar incompleto.
 * - Ativa: aprovada e completa (ver EquipeRequest: regra para ativar).
 * - Inativa: saiu ou foi suspensa; continua no banco, sem aparecer nas escolhas.
 */

namespace App\Enums;

enum StatusEquipe: string
{
    // O texto à direita é o que fica gravado no banco (sem acento nem espaço).
    case EmHomologacao = 'em_homologacao';
    case Ativa = 'ativa';
    case Inativa = 'inativa';

    /**
     * Nome para mostrar na tela.
     *
     * @return string Ex.: "Em homologação".
     */
    public function rotulo(): string
    {
        return match ($this) {
            self::EmHomologacao => 'Em homologação',
            self::Ativa => 'Ativa',
            self::Inativa => 'Inativa',
        };
    }
}
