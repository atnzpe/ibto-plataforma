<?php

/*
 * Situação de um curso (#39 / #40). O caminho (decisão do Gleyson, 10/10/2026):
 * RASCUNHO → EM ANÁLISE (enviado para chancela) → PUBLICADO (chancelado pelo
 * IBTO) ou REJEITADO (com motivo; volta para ajustes). Depois de publicado:
 * CANCELADO (com motivo) ou ENCERRADO. Só o PUBLICADO aparece para os alunos.
 * Quem pode mudar de uma para outra fica em App\Services\FluxoCurso.
 */

namespace App\Enums;

enum SituacaoCurso: string
{
    // O texto à direita é o que fica gravado no banco.
    case Rascunho = 'rascunho';
    case EmAnalise = 'em_analise';
    case Publicado = 'publicado';
    case Rejeitado = 'rejeitado';
    case Cancelado = 'cancelado';
    case Encerrado = 'encerrado';

    /**
     * Nome para mostrar na tela.
     *
     * @return string Ex.: "Em análise".
     */
    public function rotulo(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::EmAnalise => 'Em análise',
            self::Publicado => 'Publicado',
            self::Rejeitado => 'Rejeitado',
            self::Cancelado => 'Cancelado',
            self::Encerrado => 'Encerrado',
        };
    }
}
