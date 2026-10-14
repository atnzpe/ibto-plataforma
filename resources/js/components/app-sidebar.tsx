import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    ContactRound,
    FolderGit2,
    Gauge,
    GraduationCap,
    KeyRound,
    ClipboardCheck,
    FileText,
    Landmark,
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
import { comandoGeral, dashboard, meuDossie, meusCursos } from '@/routes';
// Catálogo público de cursos (#38), para o item do menu do aluno.
import { index as catalogo } from '@/routes/catalogo';
// Lista das áreas de atuação (#37), para o item do menu.
import { index as areasAtuacao } from '@/routes/cadastros/areas-atuacao';
// Lista dos níveis (#37), para o item do menu.
import { index as niveis } from '@/routes/cadastros/niveis';
// Lista das equipes filiadas (#37), para o item do menu.
import { index as equipes } from '@/routes/cadastros/equipes';
// Lista de filiados (#37), para o item do menu.
import { index as filiados } from '@/routes/cadastros/filiados';
// Cursos e trilhas do Comando Geral (#39), para o item do menu.
import { index as cursosAdmin } from '@/routes/comando-geral/cursos';
// Matrículas (#41): conferir o PIX e confirmar, para o item do menu.
import { index as matriculasAdmin } from '@/routes/comando-geral/matriculas';
// Dados para pagamento (#41): PIX e conta, para o item do menu.
import { edit as pagamentoAdmin } from '@/routes/comando-geral/pagamento';
// Livro-caixa (#59), para o item do menu.
import { index as financeiro } from '@/routes/financeiro/lancamentos';
// Grade de papéis × permissões (#36), para o item do menu.
import { edit as permissoesAdmin } from '@/routes/admin/permissoes';
// Lista de usuários (#36), para o item do menu.
import { index as usuariosAdmin } from '@/routes/admin/usuarios';
import type { NavItem } from '@/types';

// Página inicial de aluno, instrutor e admin de equipe: "Meus cursos" (desenho
// aprovado em 10/10/2026), o dossiê e o catálogo. A equipe do IBTO não vê estes itens:
// para ela, o início é o "Comando Geral" (Gleyson, 10/10/2026).
const mainNavItems: NavItem[] = [
    {
        title: 'Meus cursos',
        href: meusCursos(),
        icon: LayoutGrid,
    },
    // Meu dossiê: graduação, certificados, cursos, presenças e o que foi trabalhado.
    {
        title: 'Meu dossiê',
        href: meuDossie(),
        icon: FileText,
    },
    {
        title: 'Catálogo de cursos',
        href: catalogo(),
        icon: BookOpen,
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
    // Cursos (#39 / #40): Comando Geral e quem propõe (instrutor, admin de equipe).
    {
        title: 'Cursos',
        href: cursosAdmin(),
        icon: GraduationCap,
        permissao: 'cursos_acessar',
    },
    // Matrículas (#41): quem confirma os pagamentos PIX (secretaria/tesouraria).
    {
        title: 'Matrículas',
        href: matriculasAdmin(),
        icon: ClipboardCheck,
        permissao: 'pagamentos_confirmar',
    },
    // Dados para pagamento (#41): PIX e conta do IBTO e a chave "por curso".
    {
        title: 'Dados para pagamento',
        href: pagamentoAdmin(),
        icon: Landmark,
        permissao: 'pagamentos_configurar',
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
    // Menu: "Início" só para quem NÃO é da equipe do IBTO (a equipe começa no
    // Comando Geral) + os itens que a pessoa pode abrir.
    const itens = [
        ...(pode.comando_geral_ver ? [] : mainNavItems),
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
