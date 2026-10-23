import { createInertiaApp, router } from '@inertiajs/react';
// Rede de proteção das telas e escuta dos erros da página (#58).
import RedeDeProtecao from '@/components/rede-de-protecao';
import { escutarErrosDaJanela } from '@/lib/erros-tela';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { layoutsDaTela, telaInicial } from '@/lib/layouts-das-telas';
import type { NomeLayout } from '@/lib/layouts-das-telas';
import { lazy, Suspense } from 'react';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// MOLDURAS SOB DEMANDA (#74, desempenho, 23/10/2026). Cada moldura (layout) vira um
// arquivo separado, baixado só quando uma tela precisa dela: quem abre o login ou o
// catálogo não baixa mais o menu lateral do painel. A regra "qual tela usa qual
// moldura" mora em lib/layouts-das-telas.ts.
// "import()" (com parênteses) = baixar o arquivo quando for chamado, não no começo.
// "satisfies" confere que existe um carregador para cada nome de moldura, sem perder o tipo de cada um.
const CARREGAR_LAYOUT = {
    app: () => import('@/layouts/app-layout'),
    auth: () => import('@/layouts/auth-layout'),
    settings: () => import('@/layouts/settings/layout'),
    vitrine: () => import('@/layouts/vitrine-layout'),
} satisfies Record<NomeLayout, () => Promise<unknown>>;

// As molduras como componentes "preguiçosos" (lazy): o React espera o arquivo
// chegar e só então desenha. Usam os mesmos carregadores acima, então um arquivo
// já adiantado não é baixado de novo (o navegador reaproveita o mesmo pedido).
const LAYOUTS = {
    app: lazy(CARREGAR_LAYOUT.app),
    auth: lazy(CARREGAR_LAYOUT.auth),
    settings: lazy(CARREGAR_LAYOUT.settings),
    vitrine: lazy(CARREGAR_LAYOUT.vitrine),
};

// ADIANTAR A MOLDURA DA PRIMEIRA TELA: lê o nome da tela que o servidor mandou e
// já começa a baixar a moldura dela, junto com a própria tela (em paralelo, sem
// uma esperar a outra). Se não achar o nome, nada muda: a moldura é baixada
// quando a tela montar.
const primeiraTela = telaInicial(document);
if (primeiraTela !== null) {
    layoutsDaTela(primeiraTela).forEach((nome) => void CARREGAR_LAYOUT[nome]());
}

// DEPOIS QUE A PÁGINA CARREGAR, baixa as outras molduras com calma: assim, ao
// navegar (ex.: do catálogo para o painel), a moldura nova já está pronta e a
// tela não pisca em branco. Espera o "load" para não disputar internet com a
// primeira tela; requestIdleCallback = "quando o navegador estiver folgado"
// (onde ele não existe, um atraso de 2 segundos faz o mesmo papel).
window.addEventListener('load', () => {
    const adiantarTodas = () =>
        Object.values(CARREGAR_LAYOUT).forEach((carregar) => void carregar());

    if ('requestIdleCallback' in window) {
        window.requestIdleCallback(adiantarTodas);
    } else {
        setTimeout(adiantarTodas, 2000);
    }
});

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    // Qual moldura cada tela usa (regra em lib/layouts-das-telas.ts). Nenhuma = null
    // (telas de imprimir); uma = a própria; duas = lista (ex.: painel + abas das configurações).
    layout: (name) => {
        const molduras = layoutsDaTela(name).map((nome) => LAYOUTS[nome]);

        // Tela de imprimir: sem moldura.
        if (molduras.length === 0) {
            return null;
        }

        // Uma moldura só: devolve o componente; mais de uma, a lista (de fora para dentro).
        return molduras.length === 1 ? molduras[0] : molduras;
    },
    strictMode: true,
    withApp(app) {
        return (
            // Rede de proteção (#58): se uma tela quebrar, mostra "Algo deu errado"
            // em vez de tela branca, e avisa o servidor.
            <RedeDeProtecao>
                <TooltipProvider delayDuration={0}>
                    {/* Suspense: enquanto o arquivo da moldura chega (só na 1ª vez), não
                        desenha nada; ao chegar, a tela aparece já completa (sem "pular"). */}
                    <Suspense fallback={null}>{app}</Suspense>
                    <Toaster />
                </TooltipProvider>
            </RedeDeProtecao>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Erros "soltos" da página (fora das telas React) também avisam o servidor (#58).
escutarErrosDaJanela();

// VERSÃO NO CONSOLE (#75, pedido do Gleyson: build, versão e release do front, do back e do
// banco no log): na primeira tela, o console do navegador (F12) mostra o que está no ar.
// Uma vez por visita: as navegações seguintes não repetem.
let versaoMostrada = false;
router.on('navigate', (evento) => {
    // Já mostrou nesta visita: não repete.
    if (versaoMostrada) {
        return;
    }
    versaoMostrada = true;
    const versao = evento.detail.page.props.versao as
        | {
              numero: string;
              commit: string | null;
              front_build: string | null;
              banco: { versao: string | null; migracao: string | null };
          }
        | undefined;
    // Sem a versão (situação rara): nada a mostrar.
    if (!versao) {
        return;
    }
    console.info(
        '%cIBTO plataforma',
        'font-weight:bold;color:#6b5d3a',
        `
  back:  v${versao.numero}${versao.commit ? ` · commit ${versao.commit}` : ' · computador do desenvolvedor'}`,
        `
  front: build ${versao.front_build ?? 'em desenvolvimento (Vite)'}`,
        `
  banco: ${versao.banco.versao ?? '—'} · última migration ${versao.banco.migracao ?? '—'}`,
    );
});
