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
     * @param  string  $texto  O que a pessoa digitou. Ex.: '90', '90,5', 'R$ 1.234,56', '12.50'
     * @return int|null Os centavos (ex.: 9000, 9050, 123456, 1250), ou null se não for um valor válido.
     */
    public static function paraCentavos(string $texto): ?int
    {
        // Tira "R$" e espaços: 'R$ 1.234,56' → '1.234,56'.
        $limpo = str_replace(['R$', ' '], '', trim($texto));

        // Sem vírgula e terminando em ponto + 1 ou 2 números (ex.: '12.50', jeito
        // comum no celular): o ponto é a vírgula dos centavos → '12,50'. Com 3
        // números depois do ponto (ex.: '1.250'), ele continua sendo de milhar.
        if (! str_contains($limpo, ',') && preg_match('/^(\d+)\.(\d{1,2})$/', $limpo) === 1) {
            $limpo = str_replace('.', ',', $limpo);
        }

        // Tira os pontos de milhar que sobraram: '1.234,56' → '1234,56'.
        $limpo = str_replace('.', '', $limpo);

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

    /**
     * Divide um valor em parcelas que SOMAM EXATAMENTE o total. Os centavos que
     * sobram da divisão vão para a 1ª parcela (como no cartão).
     *
     * @param  int  $totalCentavos  O valor inteiro. Ex.: 1000 (R$ 10,00)
     * @param  int  $parcelas  Em quantas vezes. Ex.: 3
     * @return list<int> Ex.: [334, 333, 333] (soma 1000)
     */
    public static function dividirEmParcelas(int $totalCentavos, int $parcelas): array
    {
        // Divisão inteira (Gleyson): quanto cada parcela vale, sem os centavos
        // que sobram. Ex.: 1000 / 3 = 333,333... → 333 centavos por parcela.
        $base = intdiv($totalCentavos, $parcelas);
        // Resto da divisão (o "%"): os centavos que não dividiram. Ex.: 1000 % 3 = 1.
        $sobra = $totalCentavos % $parcelas;

        // Todas as parcelas começam com a base. Ex.: [333, 333, 333].
        $valores = array_fill(0, $parcelas, $base);
        // A sobra vai para a 1ª parcela. Ex.: [334, 333, 333].
        $valores[0] += $sobra;

        return $valores;
    }

    /**
     * Centavos → texto para o campo do formulário (sem "R$" e sem milhar).
     *
     * @param  int  $centavos  Ex.: 1250
     * @return string Ex.: '12,50'
     */
    public static function paraCampo(int $centavos): string
    {
        // number_format(valor, 2 casas, vírgula nos centavos, sem separador de milhar).
        return number_format($centavos / 100, 2, ',', '');
    }
}
