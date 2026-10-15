/**
 * BOTÃO "SUPORTE TÉCNICO" (pedido do Gleyson, 15/10/2026): abre o WhatsApp do
 * suporte com uma MENSAGEM PADRONIZADA, para quem atende saber na hora de qual
 * sistema e de qual tela a pessoa está falando:
 *
 *   Olá! Preciso de suporte técnico.
 *   Sistema: Plataforma de Cursos do IBTO
 *   Tela: https://cursos-homolog.ib-to.org/meus-cursos
 *   Usuário: Fulano de Tal (ou "visitante, sem login")
 *   O que aconteceu:
 *
 * O número vem do servidor (SUPORTE_WHATSAPP no .env). Sem número, o botão não
 * aparece. Dois jeitos de mostrar: "menu" (item do menu lateral, com o ícone)
 * e "link" (texto simples, nas telas públicas e nas de entrar).
 */
import { usePage } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { linkDoSuporte } from '@/lib/suporte';

/**
 * @param jeito 'menu' (item do menu lateral) ou 'link' (texto simples). Ex.: 'link'
 * @returns O botão, ou nada quando não há número do suporte.
 */
export default function BotaoSuporte({
    jeito = 'link',
}: {
    jeito?: 'menu' | 'link';
}) {
    const pagina = usePage();
    const { suporte_whatsapp, auth } = pagina.props;
    // A tela atual: o endereço completo no navegador. No servidor (a primeira
    // montagem da página, sem "window"), só o caminho. Ex.: '/blog'.
    const tela =
        typeof window !== 'undefined'
            ? window.location.origin + pagina.url
            : pagina.url;

    // Sem número no .env: o botão não aparece.
    if (!suporte_whatsapp) {
        return null;
    }

    const href = linkDoSuporte(suporte_whatsapp, tela, auth.user?.name ?? null);

    // Item do menu lateral (com o ícone, e o nome no balão quando o menu está recolhido).
    if (jeito === 'menu') {
        return (
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        asChild
                        tooltip={{ children: 'Suporte técnico' }}
                    >
                        {/* target="_blank": abre o WhatsApp em outra aba, sem perder a tela. */}
                        <a
                            href={href}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <MessageCircle aria-hidden="true" />
                            <span>Suporte técnico</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        );
    }

    // Texto simples com o ícone (telas públicas e as de entrar).
    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex min-h-11 items-center gap-2 text-sm underline underline-offset-4"
        >
            <MessageCircle aria-hidden="true" className="size-4" />
            Suporte técnico (WhatsApp)
        </a>
    );
}
