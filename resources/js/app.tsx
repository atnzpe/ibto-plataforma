import { createInertiaApp } from '@inertiajs/react';
// Rede de proteção das telas e escuta dos erros da página (#58).
import RedeDeProtecao from '@/components/rede-de-protecao';
import { escutarErrosDaJanela } from '@/lib/erros-tela';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
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
