/**
 * MENU LATERAL EM GRUPOS (18/10/2026, pedido do Gleyson: "organizado por tópicos
 * principais e subtópicos, evitando uma lista enorme" — pilares de DCU e UX).
 *
 * - Os itens se juntam por assunto: Início, Minha área, Ensino, Pessoas,
 *   Financeiro, Comunicação e Cadastros (na ordem de GRUPOS abaixo).
 * - Cada grupo abre e fecha no título (botão com aria-expanded: o leitor de tela
 *   diz "expandido/recolhido"). O grupo da tela aberta já começa aberto.
 * - A escolha de cada pessoa (aberto/fechado) fica guardada no navegador.
 * - Grupo com um item só não tem título: o item aparece direto (menos cliques).
 * - Menu recolhido em ícones: todos os itens aparecem (não há título para clicar).
 * Cada pessoa só vê os itens que a permissão dela libera (filtro no AppSidebar).
 */
import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

/** Os grupos do menu, na ordem em que aparecem, com o título de cada um. */
const GRUPOS: { chave: string; titulo: string }[] = [
    { chave: 'inicio', titulo: 'Início' },
    { chave: 'minha', titulo: 'Minha área' },
    { chave: 'ensino', titulo: 'Ensino' },
    { chave: 'pessoas', titulo: 'Pessoas' },
    { chave: 'financeiro', titulo: 'Financeiro' },
    { chave: 'comunicacao', titulo: 'Comunicação' },
    { chave: 'cadastros', titulo: 'Cadastros' },
    // Item sem grupo (raro): cai aqui, para não sumir do menu.
    { chave: 'plataforma', titulo: 'Plataforma' },
];

// Onde o navegador guarda os grupos que a pessoa fechou.
const CHAVE_GUARDADA = 'ibto-menu-grupos-fechados';

/**
 * Lê do navegador os grupos fechados (se o navegador bloquear, nenhum fechado).
 *
 * @returns Ex.: ['cadastros', 'comunicacao']
 */
function lerFechados(): string[] {
    try {
        const guardado = window.localStorage.getItem(CHAVE_GUARDADA);
        const lista: unknown = guardado ? JSON.parse(guardado) : [];

        return Array.isArray(lista)
            ? lista.filter((item): item is string => typeof item === 'string')
            : [];
    } catch {
        return [];
    }
}

/**
 * @param items Os itens que a pessoa pode abrir, cada um com o seu grupo.
 * @returns O menu lateral agrupado.
 */
export function NavMain({ items }: { items: NavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();
    // Menu recolhido em ícones: mostra todos os itens (sem títulos de grupo).
    const { state, isMobile } = useSidebar();
    const soIcones = state === 'collapsed' && !isMobile;
    // Os grupos que a pessoa fechou (guardados no navegador).
    const [fechados, setFechados] = useState<string[]>([]);

    // Lê a escolha guardada depois de abrir a tela (no navegador, não antes).
    useEffect(() => setFechados(lerFechados()), []);

    /**
     * Abre ou fecha um grupo e guarda a escolha.
     *
     * @param chave O grupo. Ex.: 'cadastros'
     */
    const alternar = (chave: string) =>
        setFechados((atual) => {
            const novo = atual.includes(chave)
                ? atual.filter((item) => item !== chave)
                : [...atual, chave];
            try {
                window.localStorage.setItem(
                    CHAVE_GUARDADA,
                    JSON.stringify(novo),
                );
            } catch {
                // Navegador sem armazenamento (ex.: janela anônima): só não guarda.
            }

            return novo;
        });

    // Os grupos com pelo menos um item, na ordem de GRUPOS.
    const grupos = GRUPOS.map((grupo) => ({
        ...grupo,
        itens: items.filter(
            (item) =>
                (item.grupo ?? 'plataforma') === grupo.chave ||
                (grupo.chave === 'plataforma' &&
                    !GRUPOS.some((outro) => outro.chave === item.grupo)),
        ),
    })).filter((grupo) => grupo.itens.length > 0);

    /**
     * Um item do menu (link com ícone).
     *
     * @param item O item. Ex.: { title: 'Cursos', href: '/comando-geral/cursos' }
     */
    const linha = (item: NavItem) => (
        <SidebarMenuItem key={item.title}>
            <SidebarMenuButton
                asChild
                isActive={isCurrentUrl(item.href)}
                tooltip={{ children: item.title }}
            >
                <Link href={item.href} prefetch>
                    {item.icon && <item.icon />}
                    <span>{item.title}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );

    return (
        <>
            {grupos.map((grupo) => {
                // O grupo tem a tela aberta? Então fica aberto, mesmo se a pessoa o fechou.
                const temAtual = grupo.itens.some((item) =>
                    isCurrentUrl(item.href),
                );
                const aberto =
                    soIcones || temAtual || !fechados.includes(grupo.chave);
                const idLista = `menu-grupo-${grupo.chave}`;

                // Um item só: sem título nem botão de abrir/fechar.
                if (grupo.itens.length === 1) {
                    return (
                        <SidebarGroup key={grupo.chave} className="px-2 py-0">
                            <SidebarMenu>{grupo.itens.map(linha)}</SidebarMenu>
                        </SidebarGroup>
                    );
                }

                return (
                    <SidebarGroup key={grupo.chave} className="px-2 py-0">
                        {/* O título do grupo é um botão: abre e fecha a lista. */}
                        <SidebarGroupLabel asChild>
                            <button
                                type="button"
                                onClick={() => alternar(grupo.chave)}
                                aria-expanded={aberto}
                                aria-controls={idLista}
                                className="flex min-h-9 w-full items-center justify-between"
                            >
                                {grupo.titulo}
                                <ChevronDown
                                    aria-hidden="true"
                                    className={`size-4 transition-transform motion-reduce:transition-none ${aberto ? '' : '-rotate-90'}`}
                                />
                            </button>
                        </SidebarGroupLabel>
                        {aberto && (
                            <SidebarMenu id={idLista}>
                                {grupo.itens.map(linha)}
                            </SidebarMenu>
                        )}
                    </SidebarGroup>
                );
            })}
        </>
    );
}
