/**
 * QUAIS TELAS TÊM TOUR (18/10/2026, desempenho do Lighthouse): uma lista
 * CURTA, carregada em toda tela, só para decidir se o botão "?" aparece e se o
 * tour abre sozinho. Os TEXTOS dos tours (lib/tours.ts, ~30 KB) e a biblioteca
 * dos balões (driver.js) só são baixados quando o tour vai abrir de fato.
 *
 * Regra da casa (17/10/2026): TODA tela tem tour, menos as desta lista, cada
 * uma com o motivo. O teste tests/js/tours.test.ts confere que as duas listas
 * (esta e a dos textos) combinam: tela nova sem tour reprova a CI.
 */

/** As telas SEM tour, com o motivo de cada uma. */
export const TELAS_SEM_TOUR: Record<string, string> = {
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
    'graduacoes/ver': 'página de impressão',
};

/**
 * A tela tem tour? (sem baixar os textos dos tours)
 *
 * @param tela O nome da tela. Ex.: 'comando-geral'
 * @returns true para toda tela fora da lista TELAS_SEM_TOUR.
 */
export function telaTemTour(tela: string): boolean {
    return !(tela in TELAS_SEM_TOUR);
}

/**
 * O tour desta tela deve abrir sozinho? (sem baixar os textos dos tours)
 *
 * @param tela O nome da tela. Ex.: 'comando-geral'
 * @param ativo O interruptor do tour na conta da pessoa.
 * @param vistos As telas cujo tour ela já viu. Ex.: ['meus-cursos']
 * @returns true só com o tour ligado, a tela com tour e ainda não vista.
 */
export function deveAbrirSozinho(
    tela: string,
    ativo: boolean,
    vistos: string[],
): boolean {
    return ativo && telaTemTour(tela) && !vistos.includes(tela);
}
