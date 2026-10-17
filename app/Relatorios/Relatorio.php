<?php

/*
 * MOLDE DE UM RELATÓRIO (#73). Cada relatório diz: o nome, a permissão, as
 * colunas (com o tipo de cada uma) e como montar as linhas no período. A tela,
 * o CSV e a versão para impressão (PDF) são os MESMOS para todos: quem cuida
 * disso é o RelatorioController. Como um formulário padrão de inventário:
 * muda o conteúdo, o cabeçalho e o rodapé são sempre iguais.
 *
 * LGPD: nenhum relatório traz CPF, nascimento, endereço ou telefone.
 */

namespace App\Relatorios;

use App\Models\User;
use App\Support\Periodo;

abstract class Relatorio
{
    /**
     * O identificador no endereço. Ex.: 'filiados' (/relatorios/filiados).
     */
    abstract public function chave(): string;

    /**
     * O título. Ex.: 'Filiados por equipe'.
     */
    abstract public function titulo(): string;

    /**
     * Uma frase do que o relatório mostra (aparece no índice e no topo).
     */
    abstract public function descricao(): string;

    /**
     * A permissão para ver. Ex.: 'relatorio.filiados'.
     */
    abstract public function permissao(): string;

    /**
     * As colunas, na ordem. Tipos: 'texto', 'numero', 'dinheiro' (centavos), 'percentual'.
     *
     * @return list<array{chave: string, rotulo: string, tipo: string}>
     */
    abstract public function colunas(): array;

    /**
     * As linhas do relatório no período, para esta pessoa (cada uma: chave da coluna => valor).
     *
     * @param  Periodo  $periodo  De/Até.
     * @param  User  $usuario  Quem pediu (alguns relatórios mostram só o que é dela).
     * @return list<array<string, mixed>> Uma lista simples (posições 0, 1, 2…).
     */
    abstract public function linhas(Periodo $periodo, User $usuario): array;

    /**
     * A linha de totais: soma as colunas de número e dinheiro; percentual e texto ficam vazios.
     *
     * @param  list<array<string, mixed>>  $linhas
     * @return array<string, mixed>
     */
    public function totais(array $linhas): array
    {
        $linhas = collect($linhas);
        $totais = [];
        foreach ($this->colunas() as $coluna) {
            $totais[$coluna['chave']] = in_array($coluna['tipo'], ['numero', 'dinheiro'], true)
                ? (int) $linhas->sum($coluna['chave'])
                : null;
        }

        return $totais;
    }
}
