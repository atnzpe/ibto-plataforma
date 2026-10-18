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
    test('os seis perfis existem e cada seção tem passos', () => {
        expect(MANUAIS.map((manual) => manual.id)).toEqual([
            'aluno',
            'instrutor',
            'admin-equipe',
            // Fotógrafo credenciado (#90).
            'fotografo',
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
            '/minha-carteirinha': 'carteirinha/minha',
            '/fotos': 'fotos/index',
            // Aulas regulares (#80).
            '/aulas': 'aulas-regulares/index',
            '/comando-geral/programas': 'aulas-regulares/admin/index',
            '/minhas-turmas': 'aulas-regulares/turmas',
            '/comando-geral/mensalidades': 'aulas-regulares/admin/mensalidades',
            '/comando-geral': 'comando-geral',
            '/comando-geral/cursos': 'cursos/index',
            '/comando-geral/matriculas': 'cursos/matriculas',
            '/financeiro/lancamentos': 'financeiro/lancamentos/index',
            // Relatórios (#73).
            '/relatorios': 'relatorios/index',
            // Meu painel (#79).
            '/meu-painel': 'meu-painel',
            // Venda de fotos (#90).
            '/comando-geral/fotos': 'fotos/admin/index',
            '/fotografo': 'fotos/fotografo/index',
            // Cupons de desconto (#89).
            '/financeiro/cupons': 'financeiro/cupons/index',
            '/cadastros/filiados': 'cadastros/filiados/index',
            '/cadastros/equipes': 'cadastros/equipes/index',
            '/admin/usuarios': 'admin/usuarios/index',
            '/comando-geral/blog': 'blog-admin/index',
            '/comando-geral/pagamento': 'cursos/pagamento',
            '/admin/permissoes': 'admin/permissoes',
            '/comando-geral/galeria': 'galeria-admin/index',
            '/cadastros/areas-atuacao': 'cadastros/areas-atuacao/index',
            '/cadastros/niveis': 'cadastros/niveis/index',
            '/settings/meus-dados': 'settings/meus-dados',
            '/settings/profile': 'settings/profile',
            '/admin/solicitacoes': 'admin/solicitacoes/index',
        };
        for (const manual of MANUAIS) {
            for (const secao of manual.secoes) {
                if (!secao.tela) {
                    continue;
                }
                expect(secao.tela.endereco.startsWith('/')).toBe(true);
                expect(secao.tela.endereco in telaDoEndereco).toBe(true);
                // Nível militar (17/10/2026): toda tela citada no manual TEM tour (sem exceção).
                expect(telaDoEndereco[secao.tela.endereco] in TOURS).toBe(true);
            }
        }
    });

    // Nível militar (17/10/2026): TODO item do menu lateral tem uma seção no manual que
    // aponta para ele ("Mostrar na tela"). Item novo no menu sem manual reprova a CI.
    test('todo item do menu tem uma seção no manual', () => {
        const menu = [
            '/meus-cursos',
            '/meu-dossie',
            '/cursos',
            '/aulas',
            '/comando-geral',
            '/comando-geral/cursos',
            '/comando-geral/matriculas',
            '/comando-geral/programas',
            '/minhas-turmas',
            '/comando-geral/mensalidades',
            '/comando-geral/pagamento',
            '/comando-geral/blog',
            '/comando-geral/galeria',
            '/cadastros/areas-atuacao',
            '/cadastros/niveis',
            '/cadastros/equipes',
            '/cadastros/filiados',
            '/financeiro/lancamentos',
            '/financeiro/cupons',
            '/minha-carteirinha',
            '/relatorios',
            '/meu-painel',
            '/fotos',
            '/comando-geral/fotos',
            '/fotografo',
            '/admin/solicitacoes',
            '/admin/usuarios',
            '/admin/permissoes',
        ];
        const noManual = new Set(
            MANUAIS.flatMap((manual) =>
                manual.secoes.map((secao) => secao.tela?.endereco),
            ),
        );
        const faltam = menu.filter((endereco) => !noManual.has(endereco));

        // A mensagem do erro lista os itens do menu que faltam no manual.
        expect(faltam).toEqual([]);
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
