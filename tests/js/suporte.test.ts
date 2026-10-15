/**
 * Testes do link do SUPORTE TÉCNICO pelo WhatsApp (resources/js/lib/suporte.ts).
 * Rodam sem navegador: `bun test tests/js`. Número e nome INVENTADOS.
 */
import { describe, expect, test } from 'bun:test';
import { linkDoSuporte } from '../../resources/js/lib/suporte';

describe('linkDoSuporte', () => {
    test('abre o wa.me do número, com a mensagem padronizada', () => {
        const link = linkDoSuporte(
            '5581999990000',
            'https://cursos.ib-to.org/blog',
            'Fulano de Tal',
        );

        expect(link.startsWith('https://wa.me/5581999990000?text=')).toBe(true);
        // A mensagem, decodificada, traz o sistema, a tela e quem pede.
        const mensagem = decodeURIComponent(link.split('?text=')[1]);
        expect(mensagem).toContain('Sistema: Plataforma de Cursos do IBTO');
        expect(mensagem).toContain('Tela: https://cursos.ib-to.org/blog');
        expect(mensagem).toContain('Usuário: Fulano de Tal');
    });

    test('visitante sem login aparece como visitante', () => {
        const mensagem = decodeURIComponent(
            linkDoSuporte('5581999990000', '/cursos', null).split('?text=')[1],
        );

        expect(mensagem).toContain('Usuário: visitante, sem login');
    });
});
