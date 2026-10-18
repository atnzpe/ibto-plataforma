import VersaoNoAr from '@/components/versao-no-ar';
import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CalendarClock,
    ContactRound,
    Dumbbell,
    Gauge,
    GraduationCap,
    Images,
    KeyRound,
    ClipboardCheck,
    FileText,
    Landmark,
    LayoutGrid,
    Medal,
    Newspaper,
    Tags,
    UserCog,
    Users,
    Camera,
    FileChartColumn,
    IdCard,
    ChartColumn,
    TicketPercent,
    Wallet,
    UserCheck,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import BotaoSuporte from '@/components/botao-suporte';
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
// Blog (#61): notícias e eventos, para o item do menu.
// Galeria de eventos (#61, B2), para o item do menu.
import { index as galeriaAdmin } from '@/routes/comando-geral/galeria';
import { index as blogAdmin } from '@/routes/comando-geral/blog';
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
    // Aulas regulares (#80): Krav Maga, Kapap... com mensalidade; dentro, o atalho "Minhas aulas".
    {
        title: 'Aulas regulares',
        href: '/aulas',
        icon: Dumbbell,
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
    // Aulas regulares (#80): os programas e as turmas (quem cuida dos cursos).
    {
        title: 'Programas (aulas regulares)',
        href: '/comando-geral/programas',
        icon: Dumbbell,
        permissao: 'cursos_gerenciar',
    },
    // Minhas turmas (#80): a chamada das aulas regulares (instrutor e admin de equipe; o Comando Geral vê todas).
    {
        title: 'Minhas turmas',
        href: '/minhas-turmas',
        icon: ClipboardCheck,
        permissao: 'painel_instrutor',
    },
    // Mensalidades (#80): conferir o Pix e confirmar (o mesmo de quem confirma as matrículas).
    {
        title: 'Mensalidades',
        href: '/comando-geral/mensalidades',
        icon: CalendarClock,
        permissao: 'pagamentos_confirmar',
    },
    // Dados para pagamento (#41): PIX e conta do IBTO e a chave "por curso".
    {
        title: 'Dados para pagamento',
        href: pagamentoAdmin(),
        icon: Landmark,
        permissao: 'pagamentos_configurar',
    },
    // Blog (#61): notícias e eventos, com fotos e vídeos.
    {
        title: 'Blog',
        href: blogAdmin(),
        icon: Newspaper,
        permissao: 'blog_publicar',
    },
    // Galeria de eventos (#61, B2): álbuns de fotos.
    {
        title: 'Galeria de eventos',
        href: galeriaAdmin(),
        icon: Images,
        permissao: 'blog_publicar',
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
    // Cupons de desconto (#89): criar e cuidar dos cupons (Comando Geral e Tecnologia).
    {
        title: 'Cupons',
        href: '/financeiro/cupons',
        icon: TicketPercent,
        permissao: 'cupons_gerenciar',
    },
    // Minha carteirinha (#46): o cartão do filiado com o QR code, para imprimir.
    {
        title: 'Minha carteirinha',
        href: '/minha-carteirinha',
        icon: IdCard,
        permissao: 'carteirinha_ver',
    },
    // Relatórios (#73): cada pessoa vê só os relatórios que a permissão dela libera.
    {
        title: 'Relatórios',
        href: '/relatorios',
        icon: FileChartColumn,
        permissao: 'relatorios_acessar',
    },
    // Meu painel (#79): inscritos, comparecimento e valores dos cursos do instrutor ou da equipe.
    {
        title: 'Meu painel',
        href: '/meu-painel',
        icon: ChartColumn,
        permissao: 'painel_instrutor',
    },
    // Fotos dos eventos (#90, 17/10/2026): os álbuns à venda da turma e os pedidos da pessoa.
    // Aparece enquanto a loja de fotos estiver ligada.
    {
        title: 'Fotos dos eventos',
        href: '/fotos',
        icon: Camera,
        permissao: 'fotos_eventos',
    },
    // Venda de fotos (#90): interruptor, álbuns e pedidos (Comando Geral).
    {
        title: 'Venda de fotos',
        href: '/comando-geral/fotos',
        icon: Camera,
        permissao: 'fotos_gerenciar',
    },
    // Fotógrafo (#90): os álbuns dele, o envio das fotos e o financeiro dele.
    {
        title: 'Meus álbuns de fotos',
        href: '/fotografo',
        icon: Images,
        permissao: 'fotos_vender',
    },
    // Solicitações de cadastro (#84; grupo fechado): aprovar ou recusar quem pediu acesso.
    {
        title: 'Solicitações de cadastro',
        href: '/admin/solicitacoes',
        icon: UserCheck,
        permissao: 'cadastros_aprovar',
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
        // data-tour: marca para o tour guiado (#68) apontar o balão "Menu".
        <Sidebar collapsible="icon" variant="inset" data-tour="menu-lateral">
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
                {/* Suporte técnico pelo WhatsApp (15/10/2026), logo acima do nome da pessoa. */}
                <BotaoSuporte jeito="menu" />
                <NavUser />
                {/* A versão no ar (#75): ajuda o suporte a saber o que a pessoa está usando. */}
                <VersaoNoAr />
            </SidebarFooter>
        </Sidebar>
    );
}
