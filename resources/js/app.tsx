import { createInertiaApp, router } from '@inertiajs/react';
// Rede de proteção das telas e escuta dos erros da página (#58).
import RedeDeProtecao from '@/components/rede-de-protecao';
import { escutarErrosDaJanela } from '@/lib/erros-tela';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import VitrineLayout from '@/layouts/vitrine-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            // Vitrine pública de cursos (#38): cabeçalho do IBTO, sem o menu lateral.
            case name.startsWith('catalogo/'):
                return VitrineLayout;
            // Blog público (#61): a mesma vitrine do IBTO.
            case name.startsWith('blog/'):
                return VitrineLayout;
            // Galeria de eventos pública (#61, B2): a mesma vitrine.
            case name.startsWith('galeria/'):
                return VitrineLayout;
            // Certificado para imprimir (#44): página limpa, sem menu nem cabeçalho.
            case name === 'certificados/ver':
                return null;
            // Relatório para imprimir ou salvar em PDF (#73): página limpa, sem menu.
            case name === 'relatorios/imprimir':
                return null;
            // Validação pública do certificado (#44): a vitrine do IBTO.
            case name === 'certificados/validar':
                return VitrineLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            // Rede de proteção (#58): se uma tela quebrar, mostra "Algo deu errado"
            // em vez de tela branca, e avisa o servidor.
            <RedeDeProtecao>
                <TooltipProvider delayDuration={0}>
                    {app}
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
