/**
 * Avisa o servidor quando dá erro NA TELA (#58, bloco B; regra do Gleyson:
 * nenhum erro em silêncio). O servidor grava no log com o código ERRO_TELA.
 *
 * Fica separado da tela para poder ser testado sem navegador
 * (tests/js/erros-tela.test.ts).
 *
 * LGPD: manda só a mensagem do erro e o caminho da página (sem a busca "?...",
 * que pode ter dados), cortados num tamanho máximo. Nada de formulário.
 */

/** De onde veio o erro. */
export type OrigemDoErro = 'tela' | 'janela' | 'promessa';

/** O que é enviado ao servidor. */
export type AvisoDeErro = {
    mensagem: string;
    pagina: string;
    origem: OrigemDoErro;
};

// No máximo 5 avisos por página (Gleyson, 09/10/2026): se uma tela entrar em
// "loop" de erro, não inunda o servidor (que aceita até 10 por minuto por IP).
const LIMITE_DE_AVISOS = 5;

// Quantos avisos esta página já mandou (volta a zero ao recarregar a página).
let avisosEnviados = 0;

/**
 * Monta o aviso: mensagem e página cortadas no tamanho máximo, sem a busca da página.
 *
 * @param erro O erro. Ex.: new TypeError('x is undefined')
 * @param caminho O endereço da página. Ex.: '/cadastros/filiados?equipe=3'
 * @param origem De onde veio. Ex.: 'tela'
 * @returns Ex.: { mensagem: 'TypeError: x is undefined', pagina: '/cadastros/filiados', origem: 'tela' }
 */
export function montarAviso(
    erro: unknown,
    caminho: string,
    origem: OrigemDoErro,
): AvisoDeErro {
    // Erro "de verdade" tem nome e mensagem; outra coisa (ex.: um texto) vira texto.
    const mensagem =
        erro instanceof Error ? `${erro.name}: ${erro.message}` : String(erro);

    return {
        // Cortes iguais aos limites do servidor (1000 e 500 letras).
        mensagem: mensagem.slice(0, 1000),
        // Só o caminho: tudo depois do "?" fica de fora.
        pagina: caminho.split('?')[0].slice(0, 500),
        origem,
    };
}

/**
 * Manda o aviso ao servidor, respeitando o limite por página.
 * Nunca deixa um erro novo escapar daqui (o aviso é "melhor esforço").
 *
 * @param erro O erro que aconteceu.
 * @param origem De onde veio. Ex.: 'janela'
 * @returns true se mandou; false se já passou do limite.
 */
export function avisarErro(erro: unknown, origem: OrigemDoErro): boolean {
    // Passou do limite desta página: para de avisar (evita "loop" de avisos).
    if (avisosEnviados >= LIMITE_DE_AVISOS) {
        return false;
    }
    avisosEnviados++;

    try {
        void fetch('/api/erros-tela', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify(
                montarAviso(erro, window.location.pathname, origem),
            ),
            // keepalive: o aviso chega mesmo se a pessoa sair da página na hora.
            keepalive: true,
        }).catch(() => {
            // Sem internet ou servidor fora: não há mais o que fazer aqui.
        });
    } catch {
        // fetch indisponível (navegador muito antigo): ignora.
    }

    return true;
}

/**
 * Liga a escuta dos erros "soltos" da página (fora do React) e das promessas
 * que falham sem ninguém tratar. Chamada uma vez, no app.tsx.
 */
export function escutarErrosDaJanela(): void {
    window.addEventListener('error', (evento) => {
        avisarErro(evento.error ?? evento.message, 'janela');
    });
    window.addEventListener('unhandledrejection', (evento) => {
        avisarErro(evento.reason, 'promessa');
    });
}
