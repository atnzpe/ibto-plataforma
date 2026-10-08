import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    FolderGit2,
    LayoutGrid,
    Medal,
    Tags,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
// Lista das áreas de atuação (#37), para o item do menu.
import { index as areasAtuacao } from '@/routes/cadastros/areas-atuacao';
// Lista dos níveis (#37), para o item do menu.
import { index as niveis } from '@/routes/cadastros/niveis';
// Lista das equipes filiadas (#37), para o item do menu.
import { index as equipes } from '@/routes/cadastros/equipes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

// Itens dos cadastros de apoio (#37), mostrados só para administradores.
const cadastrosNavItems: NavItem[] = [
    {
        title: 'Áreas de atuação',
        href: areasAtuacao(),
        icon: Tags,
    },
    {
        title: 'Níveis',
        href: niveis(),
        icon: Medal,
    },
    {
        title: 'Equipes',
        href: equipes(),
        icon: Users,
    },
];

export function AppSidebar() {
    // pode.administrar vem do servidor (HandleInertiaRequests). Só esconde o
    // menu; a rota continua barrando quem não é administrador (403).
    const { pode } = usePage().props;

    // Menu principal + cadastros, quando a pessoa é administradora.
    const itens = pode.administrar
        ? [...mainNavItems, ...cadastrosNavItems]
        : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={itens} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
