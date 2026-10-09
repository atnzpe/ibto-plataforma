import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            // O que o usuário logado pode fazer (HandleInertiaRequests). Só
            // esconde ou mostra menus; quem garante a permissão são as rotas.
            pode: {
                cadastros_areas: boolean;
                cadastros_niveis: boolean;
                cadastros_equipes: boolean;
                filiados_gerenciar: boolean;
                filiados_acessar: boolean;
                usuarios_gerenciar: boolean;
                permissoes_gerenciar: boolean;
                financeiro_ver: boolean;
            };
            // Mensagem de sucesso da última ação (ex.: "Área cadastrada."), ou null.
            sucesso: string | null;
            [key: string]: unknown;
        };
    }
}
