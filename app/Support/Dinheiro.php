<?php

/*
 * DINHEIRO (#59): converte o valor digitado na tela ("1.234,56") para centavos
 * (123456), que é como o financeiro guarda (número inteiro não erra conta).
 */

namespace App\Support;

class Dinheiro
{
    /**
     * Texto em reais → centavos. Aceita o jeito brasileiro de escrever.
     *
     * @param  string  $texto  O que a pessoa digitou. Ex.: '90', '90,5', 'R$ 1.234,56'
     * @return int|null Os centavos (ex.: 9000, 9050, 123456), ou null se não for um valor válido.
     */
    public static function paraCentavos(string $texto): ?int
    {
        // Tira "R$", espaços e os pontos de milhar: 'R$ 1.234,56' → '1234,56'.
        $limpo = str_replace(['R$', ' ', '.'], '', trim($texto));

        // Só aceita números, com no máximo 2 casas depois da vírgula. Se não
        // for assim (ex.: 'abc' ou '1,234'), devolve null e o formulário mostra o erro.
        if (preg_match('/^(\d+)(?:,(\d{1,2}))?$/', $limpo, $partes) !== 1) {
            return null;
        }

        // Reais × 100 + centavos ("5" depois da vírgula = 50 centavos, por isso o str_pad).
        $reais = (int) $partes[1];
        $centavos = (int) str_pad($partes[2] ?? '0', 2, '0');

        return $reais * 100 + $centavos;
    }
}
