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
            // Número do suporte técnico no WhatsApp (só números); null = sem botão.
            suporte_whatsapp: string | null;
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
                comando_geral_ver: boolean;
                cursos_acessar: boolean;
                pagamentos_confirmar: boolean;
                pagamentos_configurar: boolean;
                // Blog (#61): escrever, publicar e arquivar posts.
                blog_publicar: boolean;
                // Solicitações de cadastro (#84): Comando Geral, Tecnologia e admin de equipe.
                cadastros_aprovar: boolean;
                // Cupons de desconto (#89).
                cupons_gerenciar: boolean;
                // Venda de fotos (#90): Comando Geral e fotógrafo.
                fotos_gerenciar: boolean;
                fotos_vender: boolean;
                // Meu painel (#79): instrutor e admin de equipe.
                painel_instrutor: boolean;
                // Relatórios (#73).
                relatorios_acessar: boolean;
            };
            // Tour guiado (#68): o interruptor e as telas já vistas (null sem login).
            tour: { ativo: boolean; vistos: string[] } | null;
            // Mensagem de sucesso da última ação (ex.: "Área cadastrada."), ou null.
            sucesso: string | null;
            [key: string]: unknown;
        };
    }
}
