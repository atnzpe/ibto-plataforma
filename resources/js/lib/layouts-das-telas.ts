/**
 * QUAL MOLDURA (LAYOUT) CADA TELA USA — fonte única da regra (#74, 23/10/2026).
 *
 * "Layout" é a moldura em volta da tela: o menu lateral do painel, o cabeçalho
 * da vitrine pública, a caixa centralizada do login etc. Antes, o app.tsx
 * importava as quatro molduras logo no começo, e quem abria só o login ou o
 * catálogo baixava também o menu lateral do painel (que ele nem vê). Agora
 * cada moldura é baixada só quando uma tela precisa dela.
 *
 * Esta função só DIZ o nome da moldura (sem importar nada pesado). O app.tsx usa
 * a resposta para (1) montar a tela e (2) já começar a baixar a moldura certa
 * enquanto a tela ainda carrega, sem esperar uma pela outra.
 */

/**
 * Os nomes das molduras que existem.
 * - 'app': painel com o menu lateral (área logada).
 * - 'auth': caixa centralizada do entrar/cadastrar.
 * - 'settings': abas das configurações da conta (vai DENTRO do 'app').
 * - 'vitrine': cabeçalho público do IBTO, sem menu lateral.
 */
export type NomeLayout = 'app' | 'auth' | 'settings' | 'vitrine';

// Telas públicas avulsas que usam a vitrine do IBTO (além das pastas catalogo/, blog/ e galeria/).
const TELAS_VITRINE = [
    // Validação pública da carteirinha (#46).
    'carteirinha/validar',
    // Validação pública do certificado (#44).
    'certificados/validar',
    // Política de privacidade (LGPD, #47).
    'privacidade',
];

// Telas para imprimir ou salvar em PDF: página limpa, sem moldura nenhuma.
const TELAS_SEM_MOLDURA = [
    // Certificado do curso (#44).
    'certificados/ver',
    // Relatório (#73).
    'relatorios/imprimir',
    // Certificado de graduação (#46, parte 2).
    'graduacoes/ver',
    // A minha carteirinha (#46).
    'carteirinha/minha',
];

// Pastas inteiras da vitrine pública: catálogo de cursos (#38), blog e galeria de eventos (#61).
const PASTAS_VITRINE = ['catalogo/', 'blog/', 'galeria/'];

/**
 * Diz quais molduras uma tela usa, da mais de fora para a mais de dentro.
 *
 * @param tela O nome da tela no Inertia. Ex.: 'auth/login', 'catalogo/index', 'settings/profile'.
 * @returns A lista de molduras. Ex.: ['auth']; ['app', 'settings'] (configurações dentro
 *          do painel); [] (tela de imprimir, sem moldura).
 */
export function layoutsDaTela(tela: string): NomeLayout[] {
    // Entrar, cadastrar, esqueci a senha...: a caixa centralizada.
    if (tela.startsWith('auth/')) {
        return ['auth'];
    }

    // Catálogo, blog e galeria: a vitrine pública.
    if (PASTAS_VITRINE.some((pasta) => tela.startsWith(pasta))) {
        return ['vitrine'];
    }

    // Telas públicas avulsas da vitrine (validações e privacidade).
    if (TELAS_VITRINE.includes(tela)) {
        return ['vitrine'];
    }

    // Telas de imprimir: nenhuma moldura.
    if (TELAS_SEM_MOLDURA.includes(tela)) {
        return [];
    }

    // Configurações da conta: as abas dentro do painel.
    if (tela.startsWith('settings/')) {
        return ['app', 'settings'];
    }

    // Todo o resto é área logada: o painel com o menu lateral.
    return ['app'];
}

/**
 * Lê, da própria página, o nome da primeira tela que o servidor mandou abrir.
 * O Laravel escreve os dados da tela num <script data-page="app"> (JSON); ler
 * isso logo no começo deixa baixar a moldura certa sem esperar o React.
 *
 * @param documento A página (no navegador, `document`). Ex.: document.
 * @returns O nome da tela (ex.: 'auth/login'), ou null se não achar ou se o JSON vier quebrado.
 */
export function telaInicial(documento: Document): string | null {
    // O <script> com os dados da primeira tela.
    const script = documento.querySelector('script[data-page="app"]');

    // Sem o script (situação rara): não dá para adiantar nada, e tudo continua funcionando.
    if (!script?.textContent) {
        return null;
    }

    try {
        // O campo "component" é o nome da tela.
        const dados = JSON.parse(script.textContent) as { component?: unknown };

        // Só aceita texto; qualquer outra coisa vira "não sei".
        return typeof dados.component === 'string' ? dados.component : null;
    } catch {
        // JSON quebrado: não adianta a moldura (ela será baixada quando a tela montar).
        return null;
    }
}
