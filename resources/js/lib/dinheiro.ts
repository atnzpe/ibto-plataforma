/**
 * DINHEIRO na tela (#59): o servidor manda os valores em centavos (9000) e a
 * tela mostra em reais, do jeito brasileiro ("R$ 90,00").
 *
 * Fica separado para poder ser testado sem navegador (tests/js/dinheiro.test.ts).
 */

/**
 * Centavos → texto em reais, no formato brasileiro.
 *
 * @param centavos O valor em centavos. Ex.: 9000, 123456, -4990
 * @returns Ex.: 'R$ 90,00', 'R$ 1.234,56', '-R$ 49,90'
 */
export function formatarReais(centavos: number): string {
    // Intl.NumberFormat é a forma mais confiável de formatar dinheiro, pois respeita o idioma e as convenções locais.
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(centavos / 100);
}
