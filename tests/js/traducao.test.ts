/**
 * Testes dos links de tradução pelo Google Tradutor (resources/js/lib/traducao.ts, #53).
 * Rodam sem navegador: `bun test tests/js`.
 */
import { describe, expect, test } from 'bun:test';
import {
    IDIOMAS,
    buscaSemGoogle,
    hostGoogle,
    hostOriginal,
    idiomaAtual,
    linkTraducao,
    traducaoDisponivel,
} from '../../resources/js/lib/traducao';

describe('hostGoogle', () => {
    test('ponto vira hífen', () => {
        expect(hostGoogle('cursos.ib.org')).toBe('cursos-ib-org');
    });

    // O caso que pega a ordem errada: se os pontos virassem hífens primeiro,
    // os hífens novos também seriam dobrados.
    test('hífen vira dois hífens, antes de trocar os pontos', () => {
        expect(hostGoogle('cursos-homolog.ib-to.org')).toBe(
            'cursos--homolog-ib--to-org',
        );
    });
});

describe('traducaoDisponivel', () => {
    test('domínio público: o Google alcança', () => {
        expect(traducaoDisponivel('cursos.ib-to.org')).toBe(true);
    });

    test('computador local e IP: o Google não alcança', () => {
        for (const host of [
            'localhost',
            'localhost:8000',
            '127.0.0.1',
            '192.168.0.10:80',
        ]) {
            expect(traducaoDisponivel(host)).toBe(false);
        }
    });
});

describe('linkTraducao', () => {
    test('página sem busca: parâmetros do Google entram com "?"', () => {
        expect(linkTraducao('es', 'cursos.ib-to.org', '/')).toBe(
            'https://cursos-ib--to-org.translate.goog/?_x_tr_sl=pt&_x_tr_tl=es&_x_tr_hl=es',
        );
    });

    test('página com busca: parâmetros do Google entram com "&"', () => {
        expect(linkTraducao('en', 'cursos.ib-to.org', '/cursos?aba=2')).toBe(
            'https://cursos-ib--to-org.translate.goog/cursos?aba=2&_x_tr_sl=pt&_x_tr_tl=en&_x_tr_hl=en',
        );
    });

    test('tira a porta e corrige o caminho sem "/"', () => {
        expect(linkTraducao('en', 'Cursos.IB-TO.org:443', '')).toBe(
            'https://cursos-ib--to-org.translate.goog/?_x_tr_sl=pt&_x_tr_tl=en&_x_tr_hl=en',
        );
    });
});

describe('idiomaAtual', () => {
    test('no nosso site é sempre português', () => {
        expect(idiomaAtual('cursos.ib-to.org', '?_x_tr_tl=es')).toBe('pt');
    });

    test('na página do Google lê o idioma de destino', () => {
        expect(
            idiomaAtual(
                'cursos-ib--to-org.translate.goog',
                '?_x_tr_sl=pt&_x_tr_tl=es',
            ),
        ).toBe('es');
    });

    test('idioma que não oferecemos vira português', () => {
        expect(
            idiomaAtual('cursos-ib--to-org.translate.goog', '?_x_tr_tl=fr'),
        ).toBe('pt');
    });
});

describe('hostOriginal', () => {
    test('desfaz o apelido do Google (o contrário da hostGoogle)', () => {
        expect(hostOriginal('cursos--homolog-ib--to-org.translate.goog')).toBe(
            'cursos-homolog.ib-to.org',
        );
    });

    test('fora do Google devolve o próprio domínio', () => {
        expect(hostOriginal('cursos.ib-to.org')).toBe('cursos.ib-to.org');
    });
});

describe('buscaSemGoogle', () => {
    test('tira só os parâmetros que o Google acrescenta', () => {
        expect(
            buscaSemGoogle(
                '?aba=2&_x_tr_sl=pt&_x_tr_tl=es&_x_tr_hl=es&_x_tr_pto=wapp',
            ),
        ).toBe('?aba=2');
    });

    test('sem nada que sobre, devolve vazio', () => {
        expect(buscaSemGoogle('?_x_tr_sl=pt&_x_tr_tl=es')).toBe('');
    });
});

describe('IDIOMAS', () => {
    test('os três idiomas da regra, com o português primeiro', () => {
        expect(IDIOMAS.map((idioma) => idioma.codigo)).toEqual([
            'pt',
            'en',
            'es',
        ]);
    });
});
