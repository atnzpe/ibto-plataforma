/**
 * Testes do MANUAL DE INSTRUÇÕES (resources/js/lib/manuais.ts, #68 parte 2):
 * busca sem diferença de acento, filtro dos manuais e conteúdo seguro.
 * Rodam sem navegador: `bun test tests/js`.
 */
import { describe, expect, test } from 'bun:test';
import {
    MANUAIS,
    manuaisVisiveis,
    secoesDaBusca,
} from '../../resources/js/lib/manuais';
import { TOURS } from '../../resources/js/lib/tours';

describe('manuaisVisiveis', () => {
    test('mostra só os manuais liberados, na ordem da página', () => {
        expect(
            manuaisVisiveis(['instrutor', 'aluno']).map((manual) => manual.id),
        ).toEqual(['aluno', 'instrutor']);
    });
});

describe('secoesDaBusca', () => {
    const aluno = MANUAIS.find((manual) => manual.id === 'aluno')!;

    test('busca vazia mostra tudo; busca acha sem acento e sem maiúscula', () => {
        expect(secoesDaBusca(aluno, '')).toHaveLength(aluno.secoes.length);
        const achadas = secoesDaBusca(aluno, 'DOSSIE').map((secao) => secao.id);
        expect(achadas).toContain('dossie');
    });

    test('texto que não existe: nenhuma seção', () => {
        expect(secoesDaBusca(aluno, 'xyzabc')).toEqual([]);
    });
});

describe('conteúdo dos manuais', () => {
    test('os cinco perfis existem e cada seção tem passos', () => {
        expect(MANUAIS.map((manual) => manual.id)).toEqual([
            'aluno',
            'instrutor',
            'admin-equipe',
            'comando-geral',
            'tecnologia',
        ]);
        for (const manual of MANUAIS) {
            for (const secao of manual.secoes) {
                expect(secao.passos.length).toBeGreaterThan(0);
            }
        }
    });

    test('ids das seções não se repetem dentro do manual (link direto funciona)', () => {
        for (const manual of MANUAIS) {
            const ids = manual.secoes.map((secao) => secao.id);
            expect(new Set(ids).size).toBe(ids.length);
        }
    });

    test('"Mostrar na tela" aponta para endereço interno e a tela tem tour', () => {
        // Endereço → nome da tela no Inertia (para conferir que o tour existe).
        const telaDoEndereco: Record<string, string> = {
            '/cursos': 'catalogo/index',
            '/meus-cursos': 'meus-cursos',
            '/meu-dossie': 'meu-dossie',
            '/comando-geral': 'comando-geral',
            '/comando-geral/cursos': 'cursos/index',
            '/comando-geral/matriculas': 'cursos/matriculas',
            '/financeiro/lancamentos': 'financeiro/lancamentos/index',
            // Cupons de desconto (#89).
            '/financeiro/cupons': 'financeiro/cupons/index',
            '/cadastros/filiados': 'cadastros/filiados/index',
            '/cadastros/equipes': 'cadastros/equipes/index',
            '/admin/usuarios': 'admin/usuarios/index',
            '/comando-geral/blog': 'blog-admin/index',
            '/comando-geral/pagamento': '',
            '/admin/permissoes': '',
            '/admin/solicitacoes': 'admin/solicitacoes/index',
        };
        for (const manual of MANUAIS) {
            for (const secao of manual.secoes) {
                if (!secao.tela) {
                    continue;
                }
                expect(secao.tela.endereco.startsWith('/')).toBe(true);
                expect(secao.tela.endereco in telaDoEndereco).toBe(true);
                const tela = telaDoEndereco[secao.tela.endereco];
                // Tela sem tour ('' na tabela) abre normalmente, só sem os balões.
                if (tela !== '') {
                    expect(tela in TOURS).toBe(true);
                }
            }
        }
    });

    test('nenhum texto tem HTML', () => {
        const textos = MANUAIS.flatMap((manual) =>
            manual.secoes.flatMap((secao) => [
                secao.titulo,
                secao.resumo,
                ...secao.passos,
                ...(secao.regras ?? []),
            ]),
        );
        for (const texto of textos) {
            expect(texto).not.toMatch(/[<>]/);
        }
    });
});
