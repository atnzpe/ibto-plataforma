<?php

/*
 * Conferência de CPF e CNPJ pelos DÍGITOS VERIFICADORES (#37).
 *
 * As duas últimas casas de um CPF ou CNPJ são calculadas a partir das outras
 * (como o dígito de um código de barras). Se a conta não bate, o número foi
 * digitado errado. Isto NÃO consulta a Receita: só confere a conta.
 */

namespace App\Support;

class Documento
{
    /**
     * Tira tudo o que não é número (pontos, traço, barra, espaços).
     *
     * @param  string  $documento  Ex.: '529.982.247-25'
     * @return string Ex.: '52998224725'
     */
    public static function apenasDigitos(string $documento): string
    {
        return (string) preg_replace('/\D/', '', $documento);
    }

    /**
     * Diz se o documento é um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.
     *
     * @param  string  $documento  Com ou sem pontuação. Ex.: '11.222.333/0001-81'
     * @return bool true se a conta dos dígitos verificadores bate.
     */
    public static function valido(string $documento): bool
    {
        $digitos = self::apenasDigitos($documento);

        return match (strlen($digitos)) {
            11 => self::cpfValido($digitos),
            14 => self::cnpjValido($digitos),
            // Outro tamanho: não é CPF nem CNPJ.
            default => false,
        };
    }

    /**
     * Confere um CPF de 11 dígitos (só números).
     *
     * @param  string  $cpf  Ex.: '52998224725'
     */
    private static function cpfValido(string $cpf): bool
    {
        // "11111111111" passa na conta, mas não é CPF de verdade: recusa números repetidos.
        if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        // Calcula o 10º e o 11º dígitos e compara com os digitados.
        for ($posicao = 9; $posicao < 11; $posicao++) {
            $soma = 0;
            // Pesos decrescentes: 10, 9, 8... (ou 11, 10, 9... no segundo dígito).
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cpf[$i] * ($posicao + 1 - $i);
            }
            $digito = (($soma * 10) % 11) % 10;

            if ((int) $cpf[$posicao] !== $digito) {
                return false;
            }
        }

        return true;
    }

    /**
     * Confere um CNPJ de 14 dígitos (só números).
     *
     * @param  string  $cnpj  Ex.: '11222333000181'
     */
    private static function cnpjValido(string $cnpj): bool
    {
        // Números repetidos ("00000000000000") não são CNPJ de verdade.
        if (preg_match('/^(\d)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        // Pesos oficiais do 13º e do 14º dígito.
        $pesos = [
            12 => [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            13 => [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];

        foreach ($pesos as $posicao => $lista) {
            $soma = 0;
            foreach ($lista as $i => $peso) {
                $soma += (int) $cnpj[$i] * $peso;
            }
            $resto = $soma % 11;
            // Resto 0 ou 1 vira dígito 0; senão, 11 menos o resto.
            $digito = $resto < 2 ? 0 : 11 - $resto;

            if ((int) $cnpj[$posicao] !== $digito) {
                return false;
            }
        }

        return true;
    }
}
