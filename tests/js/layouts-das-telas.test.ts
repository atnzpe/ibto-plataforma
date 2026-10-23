/**
 * Testes da regra "qual moldura (layout) cada tela usa" (resources/js/lib/layouts-das-telas.ts,
 * #74). A regra decide o que o navegador baixa primeiro: um erro aqui faria uma tela
 * pública aparecer com o menu do painel, ou uma tela de imprimir sair com moldura.
 * Rodam sem navegador: `bun test tests/js`.
 */
import { describe, expect, test } from 'bun:test';
import {
    layoutsDaTela,
    telaInicial,
} from '../../resources/js/lib/layouts-das-telas';

describe('layoutsDaTela', () => {
    test('entrar e cadastrar usam a caixa centralizada', () => {
        expect(layoutsDaTela('auth/login')).toEqual(['auth']);
        expect(layoutsDaTela('auth/register')).toEqual(['auth']);
    });

    test('catálogo, blog, galeria, validações e privacidade usam a vitrine', () => {
        // Uma tela de cada pasta e as avulsas.
        [
            'catalogo/index',
            'blog/ver',
            'galeria/index',
            'carteirinha/validar',
            'certificados/validar',
            'privacidade',
        ].forEach((tela) => expect(layoutsDaTela(tela)).toEqual(['vitrine']));
    });

    test('telas de imprimir não têm moldura', () => {
        [
            'certificados/ver',
            'relatorios/imprimir',
            'graduacoes/ver',
            'carteirinha/minha',
        ].forEach((tela) => expect(layoutsDaTela(tela)).toEqual([]));
    });

    test('configurações ficam dentro do painel; o resto é o painel', () => {
        expect(layoutsDaTela('settings/profile')).toEqual(['app', 'settings']);
        expect(layoutsDaTela('dashboard')).toEqual(['app']);
        expect(layoutsDaTela('cadastros/niveis/index')).toEqual(['app']);
        // "privacidade" exata é vitrine, mas um nome parecido é do painel.
        expect(layoutsDaTela('privacidade-admin')).toEqual(['app']);
    });
});

describe('telaInicial', () => {
    /**
     * Monta um "documento" de mentira só com o querySelector que a função usa.
     *
     * @param texto O conteúdo do <script data-page>, ou null para "sem script".
     * @returns Um objeto que se comporta como o document nesse ponto.
     */
    function documento(texto: string | null): Document {
        return {
            querySelector: () =>
                texto === null ? null : { textContent: texto },
        } as unknown as Document;
    }

    test('lê o nome da tela do JSON da página', () => {
        expect(
            telaInicial(documento('{"component":"auth/login","props":{}}')),
        ).toBe('auth/login');
    });

    test('sem script, JSON quebrado ou nome que não é texto: devolve null', () => {
        expect(telaInicial(documento(null))).toBeNull();
        expect(telaInicial(documento('{quebrado'))).toBeNull();
        expect(telaInicial(documento('{"component":42}'))).toBeNull();
    });
});
