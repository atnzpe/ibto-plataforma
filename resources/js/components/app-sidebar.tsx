import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    ContactRound,
    FolderGit2,
    Gauge,
    KeyRound,
    LayoutGrid,
    Medal,
    Tags,
    UserCog,
    Users,
    Wallet,
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
// Painel inicial e o painel "Comando Geral" (#59 / #45).
import { comandoGeral, dashboard } from '@/routes';
// Lista das áreas de atuação (#37), para o item do menu.
import { index as areasAtuacao } from '@/routes/cadastros/areas-atuacao';
// Lista dos níveis (#37), para o item do menu.
import { index as niveis } from '@/routes/cadastros/niveis';
// Lista das equipes filiadas (#37), para o item do menu.
import { index as equipes } from '@/routes/cadastros/equipes';
// Lista de filiados (#37), para o item do menu.
import { index as filiados } from '@/routes/cadastros/filiados';
// Livro-caixa (#59), para o item do menu.
import { index as financeiro } from '@/routes/financeiro/lancamentos';
// Grade de papéis × permissões (#36), para o item do menu.
import { edit as permissoesAdmin } from '@/routes/admin/permissoes';
// Lista de usuários (#36), para o item do menu.
import { index as usuariosAdmin } from '@/routes/admin/usuarios';
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

// Itens dos cadastros de apoio (#37), cada um com a permissão que ele pede (#36).
// "permissao" é o nome em "pode" (o ponto do slug vira "_").
const cadastrosNavItems: (NavItem & {
    permissao: keyof SharedProps['pode'];
})[] = [
    // Painel "Comando Geral" (#59 / #45): primeiro da lista, para a equipe do IBTO.
    {
        title: 'Comando Geral',
        href: comandoGeral(),
        icon: Gauge,
        permissao: 'comando_geral_ver',
    },
    {
        title: 'Áreas de atuação',
        href: areasAtuacao(),
        icon: Tags,
        permissao: 'cadastros_areas',
    },
    {
        title: 'Níveis',
        href: niveis(),
        icon: Medal,
        permissao: 'cadastros_niveis',
    },
    {
        title: 'Equipes',
        href: equipes(),
        icon: Users,
        permissao: 'cadastros_equipes',
    },
    // Filiados (#37): registro da carteirinha + conta do app.
    {
        title: 'Filiados',
        href: filiados(),
        icon: ContactRound,
        permissao: 'filiados_acessar',
    },
    // Financeiro (#59): livro-caixa (contas a receber e a pagar).
    {
        title: 'Financeiro',
        href: financeiro(),
        icon: Wallet,
        permissao: 'financeiro_ver',
    },
    // Administração (#36): papéis de cada pessoa.
    {
        title: 'Usuários',
        href: usuariosAdmin(),
        icon: UserCog,
        permissao: 'usuarios_gerenciar',
    },
    // Administração (#36): grade de papéis × permissões.
    {
        title: 'Permissões',
        href: permissoesAdmin(),
        icon: KeyRound,
        permissao: 'permissoes_gerenciar',
    },
];

// Formato das informações que o servidor manda para todas as telas.
type SharedProps = ReturnType<typeof usePage>['props'];

export function AppSidebar() {
    // pode vem do servidor (HandleInertiaRequests). Só esconde o menu; a rota
    // continua barrando quem não tem a permissão (403).
    const { pode } = usePage().props;

    // Menu principal + só os cadastros que esta pessoa pode abrir.
    const itens = [
        ...mainNavItems,
        ...cadastrosNavItems.filter((item) => pode[item.permissao]),
    ];

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
