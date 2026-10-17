/**
 * TOUR GUIADO (#68): o roteiro de cada tela.
 *
 * Cada tela da plataforma tem um tour curto, em português simples, com
 * balões que apontam para partes da tela ("aqui fica o menu", "aqui você
 * confirma o pagamento"). Os balões apontam para elementos que existem em
 * TODAS as telas (o título da página, o menu, o botão de ajuda) ou para
 * marcas `data-tour="..."` colocadas nas telas. Passo cujo elemento não
 * existe naquela hora é pulado: o tour nunca quebra porque a tela mudou.
 *
 * Este arquivo só tem dados e funções puras (testadas em tests/js/tours.test.ts);
 * quem desenha os balões é o componente components/tour-guiado.tsx.
 */

/** Um passo do tour: um balão. */
export type PassoDoTour = {
    /** Onde o balão aponta (seletor CSS). Sem elemento: balão no meio da tela. Ex.: 'main h1' */
    elemento?: string;
    /** Título do balão. Ex.: 'Menu' */
    titulo: string;
    /** Texto do balão, curto (até 2 frases). */
    texto: string;
};

// Passos do "esqueleto" da plataforma, iguais em todas as telas com login.
// Entram no primeiro tour que a pessoa vê (a apresentação da plataforma).
const PASSOS_DO_ESQUELETO: PassoDoTour[] = [
    {
        elemento: '[data-tour="menu-lateral"]',
        titulo: 'Menu',
        texto: 'Aqui ficam todas as telas que o seu papel pode abrir. No celular, o menu abre pelo botão do topo.',
    },
    {
        elemento: '[data-tour="idiomas"]',
        titulo: 'Idioma',
        texto: 'Português é o padrão. Aqui você vê como traduzir a tela para inglês ou espanhol.',
    },
    {
        elemento: '[data-tour="menu-usuario"]',
        titulo: 'Sua conta',
        texto: 'Configurações, o manual de instruções, o interruptor do tour guiado e o botão Sair.',
    },
];

// O passo final de todo tour: como rever.
const PASSO_DA_AJUDA: PassoDoTour = {
    elemento: '[data-tour="ajuda"]',
    titulo: 'Ajuda',
    texto: 'Quer ver este tour de novo? Clique no "?" a qualquer momento. Para não abrir sozinho, desligue o tour no menu da sua conta.',
};

/**
 * Os tours de cada tela, pelo nome da tela no Inertia (o caminho do arquivo
 * em resources/js/pages, sem ".tsx"). Ex.: 'cursos/matriculas'.
 */
export const TOURS: Record<string, PassoDoTour[]> = {
    'comando-geral': [
        {
            titulo: 'Bem-vindo ao Comando Geral',
            texto: 'Este é o painel da equipe do IBTO: os números da plataforma e o atalho para cada tarefa.',
        },
        {
            elemento: 'main h1',
            titulo: 'Painel',
            texto: 'Os cartões mostram filiados, cursos, matrículas e o financeiro. Clique num cartão para abrir a tela dele.',
        },
    ],
    'meus-cursos': [
        {
            titulo: 'Bem-vindo à plataforma do IBTO',
            texto: 'Aqui ficam os seus cursos, o seu progresso e os seus certificados.',
        },
        {
            elemento: 'main h1',
            titulo: 'Meus cursos',
            texto: 'Cada curso mostra a barra de progresso e o botão Começar ou Continuar. Curso presencial mostra os encontros e a sua presença.',
        },
    ],
    'meu-dossie': [
        {
            elemento: 'main h1',
            titulo: 'Meu dossiê',
            texto: 'O seu histórico no IBTO num lugar só: graduação, certificados, cursos, presenças e o que foi trabalhado em cada encontro.',
        },
    ],
    'catalogo/index': [
        {
            elemento: 'main h1',
            titulo: 'Catálogo de cursos',
            texto: 'Use os filtros por área, modalidade e a busca. Clique num curso para ver datas, valor e se inscrever.',
        },
    ],
    // Página do curso (#83): o balão do Pix só aparece para quem está aguardando pagamento
    // (sem o elemento na tela, o passo é pulado sozinho).
    'catalogo/curso': [
        {
            elemento: 'main h1',
            titulo: 'Página do curso',
            texto: 'Datas, valor, conteúdo e o botão "Garantir minha vaga". Depois da inscrição, os dados para pagar aparecem aqui.',
        },
        {
            elemento: '#titulo-pix',
            titulo: 'Pague com Pix',
            texto: 'Aponte a câmera do app do banco para o QR code ou use "Copiar código Pix": o valor já vai preenchido. Antes de confirmar, confira o nome de quem recebe.',
        },
    ],
    'cursos/assistir': [
        {
            elemento: 'main h1',
            titulo: 'Sala de aula',
            texto: 'Assista e clique em "Marcar como assistida". Encontro ao vivo: o botão "Entrar na sala" aparece 30 minutos antes do início.',
        },
    ],
    'cursos/index': [
        {
            elemento: 'main h1',
            titulo: 'Cursos',
            texto: 'Todos os cursos e a situação de cada um: rascunho, em análise, publicado ou encerrado. O instrutor propõe; o IBTO chancela e publica.',
        },
    ],
    'cursos/formulario': [
        {
            elemento: 'main h1',
            titulo: 'Cadastro do curso',
            texto: 'Preencha os dados do cartaz: datas, local, valor, vagas, instrutor e a imagem. Depois, em Conteúdo, cadastre os módulos e as aulas.',
        },
    ],
    'cursos/matriculas': [
        {
            elemento: 'main h1',
            titulo: 'Inscritos',
            texto: 'Aqui você confirma o pagamento do PIX (procure no extrato o identificador "Pix: IBTOMAT…" de cada matrícula), vê as vagas e faz a chamada de cada encontro.',
        },
    ],
    'cursos/chamada': [
        {
            elemento: 'main h1',
            titulo: 'Chamada',
            texto: 'Marque quem veio e escreva o que foi trabalhado no encontro. Isso vai para o dossiê de cada aluno.',
        },
    ],
    'cadastros/filiados/index': [
        {
            elemento: 'main h1',
            titulo: 'Filiados',
            texto: 'A rede do IBTO. Use a busca e os filtros; para trazer muitos de uma vez, use a importação da planilha.',
        },
    ],
    'cadastros/equipes/index': [
        {
            elemento: 'main h1',
            titulo: 'Equipes',
            texto: 'As equipes filiadas, com cidade, responsável e áreas. Equipe sem área não fica ativa.',
        },
    ],
    // Cupons de desconto (#89).
    'financeiro/cupons/index': [
        {
            elemento: 'main h1',
            titulo: 'Cupons de desconto',
            texto: 'Crie códigos de desconto com validade e limite de usos. O aluno digita na inscrição e o Pix já sai com o desconto. Para encerrar, desative pela edição.',
        },
    ],
    'financeiro/lancamentos/index': [
        {
            elemento: 'main h1',
            titulo: 'Livro-caixa',
            texto: 'Receitas e despesas, a receber e a pagar. Filtre por período (De/Até). Lançamento pago não muda: corrige-se com estorno.',
        },
    ],
    'admin/usuarios/index': [
        {
            elemento: 'main h1',
            titulo: 'Usuários',
            texto: 'As contas da plataforma. Aqui você dá papéis, manda convite, troca a senha e desativa uma conta.',
        },
    ],
    'blog-admin/index': [
        {
            elemento: 'main h1',
            titulo: 'Blog',
            texto: 'Notícias e eventos que aparecem no site do IBTO. Post com foto de pessoas precisa da autorização de imagem.',
        },
    ],
    'admin/solicitacoes/index': [
        {
            elemento: 'main h1',
            titulo: 'Solicitações de cadastro',
            texto: 'Pedidos de acesso em análise. Confira quem indicou e aprove ou recuse com o motivo; a pessoa recebe um e-mail.',
        },
    ],
    'ajuda/index': [
        {
            elemento: 'main h1',
            titulo: 'Manual de instruções',
            texto: 'O passo a passo de cada tarefa, com o porquê das regras. "Mostrar na tela" abre a tela já com o tour.',
        },
    ],
    'galeria-admin/index': [
        {
            elemento: 'main h1',
            titulo: 'Galeria de eventos',
            texto: 'Álbuns de fotos dos eventos. Cada foto precisa de descrição; o álbum só publica com a autorização de imagem.',
        },
    ],
};

/**
 * A tela tem tour?
 *
 * @param tela O nome da tela. Ex.: 'comando-geral'
 */
export function temTour(tela: string): boolean {
    return tela in TOURS;
}

/**
 * Os passos do tour de uma tela, prontos para mostrar.
 *
 * @param tela O nome da tela. Ex.: 'meus-cursos'
 * @param primeiroTour true quando a pessoa ainda não viu nenhum tour: entra a apresentação do menu, idioma e conta.
 * @param existe Diz se um elemento está na tela agora (no navegador: document.querySelector).
 * @returns Só os passos que dá para mostrar (sem elemento, ou com o elemento presente). Lista vazia = sem tour.
 */
export function passosDaTela(
    tela: string,
    primeiroTour: boolean,
    existe: (seletor: string) => boolean,
): PassoDoTour[] {
    // Tela sem tour: nada a mostrar.
    const daTela = TOURS[tela];
    if (!daTela) {
        return [];
    }

    // Ordem: os passos da tela; a apresentação do esqueleto (só no primeiro tour); a ajuda.
    const todos = [
        ...daTela,
        ...(primeiroTour ? PASSOS_DO_ESQUELETO : []),
        PASSO_DA_AJUDA,
    ];

    // Pula o passo cujo elemento não está na tela (ex.: o menu lateral fechado no celular).
    return todos.filter((passo) => !passo.elemento || existe(passo.elemento));
}

/**
 * O tour desta tela deve abrir sozinho?
 *
 * @param tela O nome da tela. Ex.: 'comando-geral'
 * @param ativo O interruptor do tour na conta da pessoa.
 * @param vistos As telas cujo tour ela já viu. Ex.: ['meus-cursos']
 * @returns true só com o tour ligado, a tela com tour e ainda não vista.
 */
export function abreSozinho(
    tela: string,
    ativo: boolean,
    vistos: string[],
): boolean {
    return ativo && temTour(tela) && !vistos.includes(tela);
}
