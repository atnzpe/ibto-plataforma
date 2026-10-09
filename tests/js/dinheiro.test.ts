/**
 * Testes do formato de dinheiro da tela (resources/js/lib/dinheiro.ts, #59).
 * Rodam sem navegador: `bun test tests/js`.
 *
 * O Intl usa um espaço especial ("não quebrável") entre "R$" e o número;
 * por isso os testes trocam qualquer espaço por um espaço comum antes de comparar.
 */
import { describe, expect, test } from 'bun:test';
import { formatarReais } from '../../resources/js/lib/dinheiro';

// Troca qualquer espaço (inclusive o especial) por um espaço comum.
const normal = (texto: string) => texto.replace(/\s/g, ' ');

describe('formatarReais', () => {
    test('9000 centavos = R$ 90,00', () => {
        expect(normal(formatarReais(9000))).toBe('R$ 90,00');
    });

    test('milhar com ponto e centavos com vírgula', () => {
        expect(normal(formatarReais(123456))).toBe('R$ 1.234,56');
    });

    test('negativo (saldo do mês no vermelho)', () => {
        expect(normal(formatarReais(-4990))).toBe('-R$ 49,90');
    });
});
