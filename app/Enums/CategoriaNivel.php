<?php

/*
 * As CATEGORIAS de nível do IBTO (#37), na ordem da hierarquia, com o grau
 * máximo de cada uma (lista do Gleyson, 07/10/2026):
 *   Praticante (sem grau) · Monitor I–II · Instrutor I–V · Instrutor Expert I–V
 *
 * "enum" é uma lista fechada de opções no código: o banco e as telas só
 * aceitam estes valores (como as faixas de uma arte marcial, que não se inventa).
 */

namespace App\Enums;

enum CategoriaNivel: string
{
    // O texto à direita é o que fica gravado no banco e aparece na tela.
    case Praticante = 'Praticante';
    case Monitor = 'Monitor';
    case Instrutor = 'Instrutor';
    case InstrutorExpert = 'Instrutor Expert';

    /**
     * Maior grau que a categoria aceita. 0 = a categoria não tem grau.
     *
     * @return int Ex.: Monitor devolve 2 (Monitor I e Monitor II).
     */
    public function grauMaximo(): int
    {
        // "match" é como uma tabela de preços do PDV: para cada opção, um valor.
        return match ($this) {
            self::Praticante => 0,
            self::Monitor => 2,
            self::Instrutor => 5,
            self::InstrutorExpert => 5,
        };
    }

    /**
     * Posição na hierarquia, para as listas ficarem na ordem certa
     * (Praticante primeiro, Instrutor Expert por último), e não em ordem alfabética.
     *
     * @return int Ex.: Praticante 1, Instrutor Expert 4.
     */
    public function posicao(): int
    {
        return match ($this) {
            self::Praticante => 1,
            self::Monitor => 2,
            self::Instrutor => 3,
            self::InstrutorExpert => 4,
        };
    }
}
