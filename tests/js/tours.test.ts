/**
 * Testes do TOUR GUIADO (resources/js/lib/tours.ts, #68): quais passos
 * aparecem, quando o tour abre sozinho e se todo texto cabe num balão.
 * Rodam sem navegador: `bun test tests/js`.
 */
import { describe, expect, test } from 'bun:test';
import { readdirSync } from 'node:fs';
import { join } from 'node:path';
import {
    abreSozinho,
    passosDaTela,
    TOURS,
    temTour,
} from '../../resources/js/lib/tours';

// Finge uma tela onde TODOS os elementos existem.
const tudoExiste = () => true;
// Finge uma tela onde nenhum elemento apontado existe.
const nadaExiste = () => false;

describe('passosDaTela', () => {
    test('primeiro tour: passos da tela + apresentação do menu, idioma e conta + ajuda', () => {
        const passos = passosDaTela('comando-geral', true, tudoExiste);
        const titulos = passos.map((passo) => passo.titulo);

        expect(titulos[0]).toBe('Bem-vindo ao Comando Geral');
        expect(titulos).toContain('Menu');
        expect(titulos).toContain('Sua conta');
        expect(titulos.at(-1)).toBe('Ajuda');
    });

    test('a partir do segundo tour: sem a apresentação do esqueleto', () => {
        const titulos = passosDaTela('comando-geral', false, tudoExiste).map(
            (passo) => passo.titulo,
        );

        expect(titulos).not.toContain('Menu');
        expect(titulos.at(-1)).toBe('Ajuda');
    });

    test('passo cujo elemento não está na tela é pulado (o tour não quebra)', () => {
        const passos = passosDaTela('comando-geral', true, nadaExiste);

        // Sobra só o passo sem elemento (o balão no meio da tela).
        expect(passos).toHaveLength(1);
        expect(passos[0].elemento).toBeUndefined();
    });

    test('tela sem tour (a de entrar, sem menu): nenhum passo', () => {
        expect(passosDaTela('auth/login', true, tudoExiste)).toEqual([]);
    });
});

describe('abreSozinho', () => {
    test('só com o tour ligado, numa tela com tour e ainda não vista', () => {
        expect(abreSozinho('meus-cursos', true, [])).toBe(true);
        expect(abreSozinho('meus-cursos', true, ['meus-cursos'])).toBe(false);
        expect(abreSozinho('meus-cursos', false, [])).toBe(false);
        expect(abreSozinho('auth/login', true, [])).toBe(false);
    });
});

// AUDITORIA "NÍVEL MILITAR" (17/10/2026): TODA tela da plataforma tem tour, menos
// as da lista abaixo, cada uma com o motivo. Tela nova sem tour reprova a CI.
const SEM_TOUR: Record<string, string> = {
    // Antes do login, sem menu nem botão "?": a tela é um formulário só.
    'auth/cadastro-em-analise': 'antes do login',
    'auth/confirm-password': 'antes do login',
    'auth/convite': 'antes do login',
    'auth/forgot-password': 'antes do login',
    'auth/login': 'antes do login',
    'auth/register': 'antes do login',
    'auth/reset-password': 'antes do login',
    'auth/two-factor-challenge': 'antes do login',
    'auth/verify-email': 'antes do login',
    // Páginas de impressão (sem menu): o papel não tem balão.
    'certificados/ver': 'página de impressão',
    'relatorios/imprimir': 'página de impressão',
};

describe('cobertura do tour (toda tela)', () => {
    test('toda tela em resources/js/pages tem tour (ou está na lista SEM_TOUR com motivo)', () => {
        const pasta = join(import.meta.dir, '../../resources/js/pages');
        const telas = readdirSync(pasta, { recursive: true })
            .map(String)
            .filter((arquivo) => arquivo.endsWith('.tsx'))
            .map((arquivo) =>
                arquivo.replaceAll('\\', '/').replace(/\.tsx$/, ''),
            );
        const semTour = telas.filter(
            (tela) => !(tela in TOURS) && !(tela in SEM_TOUR),
        );

        // A mensagem do erro lista as telas que faltam (para quem for corrigir).
        expect(semTour).toEqual([]);
    });
});

describe('textos dos tours', () => {
    test('toda tela com tour tem texto curto (cabe no balão do celular)', () => {
        for (const [tela, passos] of Object.entries(TOURS)) {
            expect(temTour(tela)).toBe(true);
            for (const passo of passos) {
                expect(passo.titulo.length).toBeLessThanOrEqual(40);
                expect(passo.texto.length).toBeLessThanOrEqual(200);
            }
        }
    });

    test('nenhum texto tem HTML (o balão desenha texto como HTML)', () => {
        for (const passos of Object.values(TOURS)) {
            for (const passo of passos) {
                expect(passo.titulo + passo.texto).not.toMatch(/[<>]/);
            }
        }
    });
});
