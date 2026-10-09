/**
 * Testes do aviso de erro da tela (resources/js/lib/erros-tela.ts, #58).
 * Rodam sem navegador: `bun test tests/js`.
 */
import { describe, expect, test } from 'bun:test';
import { montarAviso } from '../../resources/js/lib/erros-tela';

describe('montarAviso', () => {
    test('erro de verdade: nome e mensagem', () => {
        expect(
            montarAviso(
                new TypeError('x is undefined'),
                '/cadastros/filiados',
                'tela',
            ),
        ).toEqual({
            mensagem: 'TypeError: x is undefined',
            pagina: '/cadastros/filiados',
            origem: 'tela',
        });
    });

    test('LGPD: a busca da página ("?...") fica de fora', () => {
        expect(
            montarAviso(
                'falhou',
                '/cadastros/filiados?busca=fulano@exemplo.com',
                'janela',
            ).pagina,
        ).toBe('/cadastros/filiados');
    });

    test('mensagem enorme é cortada em 1000 letras', () => {
        expect(
            montarAviso('a'.repeat(5000), '/', 'promessa').mensagem,
        ).toHaveLength(1000);
    });
});
