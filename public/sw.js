/*
 * SERVICE WORKER DA PLATAFORMA (#74, offline first).
 *
 * O service worker é um "ajudante" que o navegador deixa rodando por trás da
 * página. Ele vê cada pedido ao servidor e decide: buscar na internet ou usar a
 * cópia guardada no aparelho. Regras (pensadas para a LGPD):
 * - Arquivos do visual (/build, /icons, /images): guarda e reaproveita (os nomes
 *   do /build mudam a cada versão, então nunca ficam velhos).
 * - Telas: busca SEMPRE na internet primeiro. Só guarda a tela que o servidor
 *   marcou como pública e sem login (cabeçalho X-Ibto-Offline: publico), ex.: o
 *   catálogo visto por um visitante. Tela com login NUNCA é guardada: num celular
 *   emprestado, outra pessoa não veria os dados de quem usou antes.
 * - Sem internet: mostra a cópia guardada, ou a página "Sem internet".
 * - Pedidos que gravam algo (POST, PUT...) e da API nunca passam pela cópia.
 */

// A versão vem no endereço do script (sw.js?v=0.2.0-a1b2c3d): versão nova = caixas novas.
const VERSAO = new URL(self.location.href).searchParams.get('v') || 'dev';
// Caixa dos arquivos do visual e caixa das telas públicas.
const CAIXA_ARQUIVOS = `ibto-arquivos-${VERSAO}`;
const CAIXA_TELAS = `ibto-telas-${VERSAO}`;
// A página mostrada quando não há internet nem cópia.
const PAGINA_OFFLINE = '/offline.html';

// Ao instalar: guarda já a página "Sem internet" e os ícones (para nunca faltarem).
self.addEventListener('install', (evento) => {
    evento.waitUntil(
        caches
            .open(CAIXA_ARQUIVOS)
            .then((caixa) =>
                caixa.addAll([PAGINA_OFFLINE, '/icons/icon-192.png']),
            )
            .then(() => self.skipWaiting()),
    );
});

// Ao ativar: apaga as caixas de versões antigas (para não ocupar espaço à toa).
self.addEventListener('activate', (evento) => {
    evento.waitUntil(
        caches
            .keys()
            .then((nomes) =>
                Promise.all(
                    nomes
                        .filter(
                            (nome) =>
                                nome.startsWith('ibto-') &&
                                nome !== CAIXA_ARQUIVOS &&
                                nome !== CAIXA_TELAS,
                        )
                        .map((nome) => caches.delete(nome)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (evento) => {
    const pedido = evento.request;
    const endereco = new URL(pedido.url);

    // Só cuida de leituras (GET) do próprio endereço da plataforma. O resto segue normal.
    if (pedido.method !== 'GET' || endereco.origin !== self.location.origin) {
        return;
    }
    // API e trocas de tela do Inertia (pedem JSON): sempre direto na internet.
    if (
        endereco.pathname.startsWith('/api/') ||
        pedido.headers.has('X-Inertia')
    ) {
        return;
    }

    // Arquivos do visual: usa a cópia; se não houver, baixa e guarda.
    if (/^\/(build|icons|images)\//.test(endereco.pathname)) {
        evento.respondWith(
            caches.match(pedido).then(
                (copia) =>
                    copia ||
                    fetch(pedido).then((resposta) => {
                        if (resposta.ok) {
                            const duplicata = resposta.clone();
                            caches
                                .open(CAIXA_ARQUIVOS)
                                .then((caixa) => caixa.put(pedido, duplicata));
                        }
                        return resposta;
                    }),
            ),
        );
        return;
    }

    // Abrir uma tela (navegação): internet primeiro.
    if (pedido.mode === 'navigate') {
        evento.respondWith(
            fetch(pedido)
                .then((resposta) => {
                    // Só guarda a tela que o servidor marcou como pública (sem login).
                    if (
                        resposta.ok &&
                        resposta.headers.get('X-Ibto-Offline') === 'publico'
                    ) {
                        const duplicata = resposta.clone();
                        caches
                            .open(CAIXA_TELAS)
                            .then((caixa) => caixa.put(pedido, duplicata));
                    }
                    return resposta;
                })
                // Sem internet: a cópia da tela, ou a página "Sem internet".
                .catch(() =>
                    caches
                        .match(pedido)
                        .then((copia) => copia || caches.match(PAGINA_OFFLINE)),
                ),
        );
    }
});
