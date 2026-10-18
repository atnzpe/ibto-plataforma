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
            texto: 'Marque quem veio e escreva o que foi trabalhado no encontro (obrigatório). Isso vai para o dossiê de cada aluno.',
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
    // Relatórios (#73).
    'relatorios/index': [
        {
            elemento: 'main h1',
            titulo: 'Relatórios',
            texto: 'Os relatórios que a sua permissão libera. Em cada um: filtro De/Até, CSV e PDF com a marca do IBTO. Toda exportação fica registrada.',
        },
    ],
    // Painel financeiro das aulas regulares (17/10/2026).
    'relatorios/aulas-regulares': [
        {
            elemento: 'main h1',
            titulo: 'Aulas regulares (financeiro)',
            texto: 'Quem treina, quanto estava previsto, quanto já entrou, quanto falta e quanto atrasou. Sem filtro, o ano atual.',
        },
        {
            elemento: '[data-tour="filtros-aulas"]',
            titulo: 'Filtros',
            texto: 'Escolha o programa, o instrutor (quem recebe o repasse), a situação, ou busque o aluno (quem paga) por parte do nome.',
        },
        {
            elemento: '[data-tour="cartoes-aulas"]',
            titulo: 'Onde estamos',
            texto: 'Previsto = soma das mensalidades que vencem no período. Recebido = Pix já confirmado. A receber = no prazo. Em atraso = venceu e não foi pago.',
        },
        {
            elemento: '[data-tour="divisao-aulas"]',
            titulo: 'Divisão do dinheiro',
            texto: 'Quanto fica com o IBTO e quanto vai para os instrutores, quanto já foi repassado e quanto falta repassar.',
        },
        {
            elemento: '[data-tour="alunos-aulas"]',
            titulo: 'Quem treina',
            texto: 'Cada aluno com o programa, a turma, a mensalidade e se está em dia, na carência ou com as aulas travadas.',
        },
        {
            elemento: '[data-tour="csv-aulas"]',
            titulo: 'Exportar',
            texto: 'O CSV traz uma linha por mensalidade, com os mesmos filtros da tela. A exportação fica registrada na auditoria.',
        },
    ],
    // Meu painel (#79).
    'meu-painel': [
        {
            elemento: 'main h1',
            titulo: 'Meu painel',
            texto: 'Os números dos seus cursos (ou da sua equipe): inscritos, comparecimento, a receber, recebido e certificados. Troque o período em De/Até e exporte em CSV.',
        },
        {
            elemento: '[data-tour="saude-financeira"]',
            titulo: 'Minha saúde financeira',
            texto: 'Cursos e aulas regulares juntos: o que você já recebeu, o que o aluno já pagou e falta o IBTO repassar, o que ainda vai vencer e o total a receber.',
        },
    ],
    // Venda de fotos (#90).
    'fotos/admin/index': [
        {
            elemento: 'main h1',
            titulo: 'Venda de fotos',
            texto: 'Ligue ou desligue a venda, crie os álbuns de cada evento (curso, fotógrafo, preço e expiração) e confirme os pedidos pelo identificador IBTOFOT no extrato.',
        },
    ],
    'fotos/fotografo/index': [
        {
            elemento: 'main h1',
            titulo: 'Meus álbuns de fotos',
            texto: 'Os álbuns em que o IBTO te escalou. Envie as fotos: a prévia com marca d\'água é gerada sozinha. Em "Meu financeiro", as suas vendas pagas.',
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
        {
            elemento: '[data-tour="cartoes-financeiro"]',
            titulo: 'Os números do filtro',
            texto: 'Recebido, a receber, pago, a pagar e o saldo em caixa somam SÓ o que está no filtro. Mudou o filtro, mudam os números.',
        },
        {
            elemento: '[data-tour="filtros-financeiro"]',
            titulo: 'Filtros avançados',
            texto: 'Pessoa (nome ou e-mail), papel (ex.: Fotógrafo → quanto pagar a cada fotógrafo), equipe, curso, programa, modalidade, código e faixa de valor.',
        },
        {
            elemento: '[data-tour="modo-financeiro"]',
            titulo: 'Analítico ou sintético',
            texto: 'Analítico: cada lançamento. Sintético: somado por pessoa, categoria, curso, equipe, mês ou forma. CSV e PDF exportam o que está na tela.',
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
    // ===== Tours completados em 17/10/2026 (auditoria "nível militar": toda tela tem tour). =====
    // Aulas regulares (#80).
    'aulas-regulares/index': [
        {
            elemento: 'main h1',
            titulo: 'Aulas regulares',
            texto: 'Aulas contínuas com mensalidade (Krav Maga, Kapap...). Escolha um programa e toque em "Ver e assinar".',
        },
    ],
    'aulas-regulares/programa': [
        {
            elemento: 'main h1',
            titulo: 'O programa',
            texto: 'Mensalidade, dia do vencimento, vagas e turmas. Escolha a turma e toque em "Assinar".',
        },
    ],
    'aulas-regulares/minhas': [
        {
            elemento: 'main h1',
            titulo: 'Minhas aulas',
            texto: 'Cada programa que você assina, com a situação e o Pix da mensalidade em aberto. Em dia, aparece o link da sala ao vivo.',
        },
    ],
    'aulas-regulares/turmas': [
        {
            elemento: 'main h1',
            titulo: 'Minhas turmas',
            texto: 'As turmas dos programas em que você é o instrutor. Toque em "Fazer chamada" na turma do dia.',
        },
    ],
    'aulas-regulares/chamada': [
        {
            elemento: 'main h1',
            titulo: 'Chamada',
            texto: 'A data já vem com hoje. Marque quem estava e toque em "Salvar chamada". Só aparecem os alunos em dia.',
        },
        {
            elemento: '[data-tour="conteudo-aula"]',
            titulo: 'O que foi ministrado',
            texto: 'Obrigatório: escreva o que foi ensinado na aula. Vai para o diário do dossiê de cada aluno.',
        },
        {
            elemento: '[data-tour="salvar-chamada"]',
            titulo: 'Salvar',
            texto: 'Salva a presença de todos de uma vez. Errou? Marque de novo e salve: corrige sem duplicar.',
        },
    ],
    'aulas-regulares/admin/index': [
        {
            elemento: 'main h1',
            titulo: 'Programas',
            texto: 'Os programas de aulas regulares. "Novo programa" cria; "Mensalidades" leva à fila do Pix.',
        },
    ],
    'aulas-regulares/admin/formulario': [
        {
            elemento: 'main h1',
            titulo: 'Programa',
            texto: 'Mensalidade em reais, parte do IBTO, vagas e dia do vencimento. Depois de salvar, cadastre as turmas.',
        },
    ],
    'aulas-regulares/admin/mensalidades': [
        {
            elemento: 'main h1',
            titulo: 'Mensalidades',
            texto: 'Confira o Pix no extrato pelo identificador (IBTOAUL…) e toque em "Confirmar". A receita e o repasse entram no livro-caixa.',
        },
    ],
    // Pagamento único (17/10/2026).
    pagar: [
        {
            elemento: 'main h1',
            titulo: 'Pagamento',
            texto: 'O resumo do que você está pagando e a situação, que muda sozinha quando o IBTO confirma.',
        },
        {
            elemento: '[data-tour="pix"]',
            titulo: 'Pix',
            texto: 'Leia o QR ou toque em copiar e cole no app do banco: o valor já vem preenchido.',
        },
    ],
    // Fotos (#90).
    'fotos/index': [
        {
            elemento: 'main h1',
            titulo: 'Fotos dos eventos',
            texto: 'Os álbuns da sua turma e os seus pedidos. Toque em "Ver as fotos" para escolher.',
        },
    ],
    'fotos/vitrine': [
        {
            elemento: '[data-tour="passos-compra"]',
            titulo: 'Três passos',
            texto: '1) Escolha as fotos; 2) pague o Pix; 3) baixe os originais. A faixa mostra onde você está.',
        },
        {
            elemento: 'main h1',
            titulo: 'Escolher',
            texto: 'Toque nas fotos que quer e depois em "Comprar com Pix". As prévias têm marca d\'água.',
        },
    ],
    'fotos/pedido': [
        {
            elemento: 'main h1',
            titulo: 'Seu pedido',
            texto: 'Aguardando: pague o Pix abaixo. Pago: os botões de baixar ficam liberados por 30 dias.',
        },
    ],
    'fotos/admin/formulario': [
        {
            elemento: 'main h1',
            titulo: 'Álbum à venda',
            texto: 'O curso, o fotógrafo, o preço por foto, a parte dele e quando as fotos expiram.',
        },
    ],
    'fotos/fotografo/album': [
        {
            elemento: 'main h1',
            titulo: 'Enviar fotos',
            texto: "Envie até 30 fotos por vez. Cada uma ganha a prévia com a marca d'água do IBTO e o seu nome.",
        },
    ],
    'fotos/fotografo/financeiro': [
        {
            elemento: 'main h1',
            titulo: 'Seu financeiro',
            texto: 'As vendas pagas e a sua parte, a receber e já recebida.',
        },
    ],
    // Galeria e blog (#61).
    'galeria-admin/formulario': [
        {
            elemento: 'main h1',
            titulo: 'Álbum',
            texto: 'Envie as fotos, descreva cada uma e marque a autorização de imagem. Só o Comando Geral publica.',
        },
        {
            elemento: '[data-tour="desfocar"]',
            titulo: 'Desfocar',
            texto: 'Arraste sobre rostos, distintivos e placas e salve. O original fica guardado só para o Comando Geral.',
        },
    ],
    'galeria/index': [
        {
            elemento: 'main h1',
            titulo: 'Eventos',
            texto: 'Os álbuns publicados. As fotos aparecem só para quem tem login (segurança dos operadores).',
        },
    ],
    'galeria/album': [
        {
            elemento: 'main h1',
            titulo: 'Álbum',
            texto: 'As fotos do evento, com descrição e crédito.',
        },
    ],
    'blog/index': [
        {
            elemento: 'main h1',
            titulo: 'Blog',
            texto: 'Notícias e eventos do IBTO. Use o filtro de categoria.',
        },
    ],
    'blog/post': [
        {
            elemento: 'main h1',
            titulo: 'Post',
            texto: 'O texto completo, com fotos e vídeo quando houver.',
        },
    ],
    'blog-admin/formulario': [
        {
            elemento: 'main h1',
            titulo: 'Post',
            texto: 'Título, categoria, resumo, texto, capa e vídeo. Publique na hora ou agende uma data.',
        },
    ],
    // Carteirinha, privacidade e conta.
    'carteirinha/minha': [
        {
            elemento: 'main section',
            titulo: 'Sua carteirinha',
            texto: 'Foto, registro, nível, equipe, validade e o QR. "Imprimir ou salvar em PDF" sai no tamanho de cartão.',
        },
    ],
    'carteirinha/validar': [
        {
            elemento: 'main h1',
            titulo: 'Validação',
            texto: 'O semáforo mostra se a carteirinha está liberada. Só aparece o necessário para conferir.',
        },
    ],
    'certificados/validar': [
        {
            elemento: 'main h1',
            titulo: 'Validação',
            texto: 'Mostra se o certificado é válido, o curso e a data, com o nome abreviado.',
        },
    ],
    privacidade: [
        {
            elemento: 'main h1',
            titulo: 'Privacidade',
            texto: 'Quais dados o IBTO guarda, para quê e por quanto tempo. Com login, você aceita ou revoga no fim.',
        },
    ],
    'settings/profile': [
        {
            elemento: 'main h1',
            titulo: 'Perfil',
            texto: 'Nome, e-mail, foto de perfil, foto 3x4 da carteirinha e a autorização de imagem.',
        },
    ],
    'settings/security': [
        {
            elemento: 'main h1',
            titulo: 'Segurança',
            texto: 'Troque a senha e ligue a verificação em duas etapas.',
        },
    ],
    'settings/appearance': [
        {
            elemento: 'main h1',
            titulo: 'Aparência',
            texto: 'Tema claro, escuro ou o do aparelho.',
        },
    ],
    'settings/meus-dados': [
        {
            elemento: 'main h1',
            titulo: 'Meus dados',
            texto: 'O que o IBTO guarda sobre você (mascarado) e quem abriu a sua ficha completa, com data e hora.',
        },
    ],
    // Catálogo e cursos.
    'catalogo/trilha': [
        {
            elemento: 'main h1',
            titulo: 'Trilha',
            texto: 'A ordem sugerida dos cursos, de cima para baixo. "Em breve" ainda não abriu inscrição.',
        },
    ],
    'cursos/detalhes': [
        {
            elemento: 'main h1',
            titulo: 'Inscritos',
            texto: 'O resumo em passos (vaga, pagamento, presença, certificado) e a lista de inscritos do curso.',
        },
        {
            elemento: '[data-tour="fotos-do-curso"]',
            titulo: 'Fotos do curso',
            texto: 'Você é o responsável pelas fotos: abra o álbum do evento ou as fotos à venda.',
        },
    ],
    'cursos/conteudo': [
        {
            elemento: 'main h1',
            titulo: 'Conteúdo',
            texto: 'Módulos, aulas, vídeos, apostilas e encontros ao vivo, na ordem em que o aluno vê.',
        },
    ],
    'cursos/matricula-ficha': [
        {
            elemento: 'main h1',
            titulo: 'Ficha',
            texto: 'Os dados da inscrição. Abrir esta ficha fica registrado (LGPD).',
        },
    ],
    'cursos/pagamento': [
        {
            elemento: 'main h1',
            titulo: 'Dados para pagamento',
            texto: 'O Pix do IBTO e, se a chave "por curso" estiver ligada, o de cada curso. Pede a senha de novo.',
        },
    ],
    'cursos/trilhas/index': [
        {
            elemento: 'main h1',
            titulo: 'Trilhas',
            texto: 'As trilhas de formação. Monte a ordem dos cursos em etapas.',
        },
    ],
    'cursos/trilhas/formulario': [
        {
            elemento: 'main h1',
            titulo: 'Trilha',
            texto: 'O nome, a descrição e os cursos de cada etapa.',
        },
    ],
    // Cadastros.
    'cadastros/areas-atuacao/index': [
        {
            elemento: 'main h1',
            titulo: 'Áreas de atuação',
            texto: 'A lista oficial. As ativas aparecem no site.',
        },
    ],
    'cadastros/areas-atuacao/formulario': [
        {
            elemento: 'main h1',
            titulo: 'Área',
            texto: 'O nome, a imagem e se está ativa.',
        },
    ],
    'cadastros/niveis/index': [
        {
            elemento: 'main h1',
            titulo: 'Níveis',
            texto: 'As graduações do IBTO, na ordem.',
        },
    ],
    'cadastros/niveis/formulario': [
        {
            elemento: 'main h1',
            titulo: 'Nível',
            texto: 'O nome e a ordem da graduação.',
        },
    ],
    'cadastros/equipes/formulario': [
        {
            elemento: 'main h1',
            titulo: 'Equipe',
            texto: 'Cidade, UF, responsável, logo e pelo menos uma área (sem área, a equipe não fica ativa).',
        },
    ],
    'cadastros/filiados/formulario': [
        {
            elemento: 'main h1',
            titulo: 'Ficha do filiado',
            texto: 'Dados, equipe, nível e anuidade. Abrir esta ficha fica registrado (LGPD). No fim, a anonimização.',
        },
    ],
    'cadastros/filiados/importar': [
        {
            elemento: 'main h1',
            titulo: 'Importar',
            texto: 'Envie a planilha. Nada é gravado antes da prévia.',
        },
    ],
    'cadastros/filiados/importar-colunas': [
        {
            elemento: 'main h1',
            titulo: 'Colunas',
            texto: 'Diga qual coluna da planilha é cada campo.',
        },
    ],
    'cadastros/filiados/importar-valores': [
        {
            elemento: 'main h1',
            titulo: 'Valores',
            texto: 'Relacione os valores da planilha (ex.: equipes) com os do cadastro.',
        },
    ],
    'cadastros/filiados/importar-previa': [
        {
            elemento: 'main h1',
            titulo: 'Prévia',
            texto: 'Confira linha por linha antes de gravar. As linhas com problema aparecem com o motivo.',
        },
    ],
    // Pessoas e acesso.
    'admin/usuarios/formulario': [
        {
            elemento: 'main h1',
            titulo: 'Usuário',
            texto: 'Os papéis da pessoa. Cada papel abre um conjunto de telas.',
        },
    ],
    'admin/permissoes': [
        {
            elemento: 'main h1',
            titulo: 'Permissões',
            texto: 'A matriz papéis × telas. Mudou aqui, vale na hora para todos daquele papel.',
        },
    ],
    // Financeiro.
    'financeiro/lancamentos/formulario': [
        {
            elemento: 'main h1',
            titulo: 'Lançamento',
            texto: 'Receita ou despesa, categoria, valor, vencimento e, se quiser, parcelas.',
        },
    ],
    'financeiro/historico': [
        {
            elemento: 'main h1',
            titulo: 'Histórico',
            texto: 'Tudo o que mudou no lançamento: quem, quando, antes e depois.',
        },
    ],
    'financeiro/categorias/index': [
        {
            elemento: 'main h1',
            titulo: 'Categorias',
            texto: 'As categorias de receita e despesa. Desative em vez de apagar.',
        },
    ],
    'financeiro/categorias/formulario': [
        {
            elemento: 'main h1',
            titulo: 'Categoria',
            texto: 'O nome, o tipo (receita ou despesa) e se está ativa.',
        },
    ],
    'financeiro/formas/index': [
        {
            elemento: 'main h1',
            titulo: 'Formas de pagamento',
            texto: 'Pix, dinheiro, cartão, transferência. Desative em vez de apagar.',
        },
    ],
    'financeiro/formas/formulario': [
        {
            elemento: 'main h1',
            titulo: 'Forma de pagamento',
            texto: 'O nome e se está ativa.',
        },
    ],
    'financeiro/cupons/formulario': [
        {
            elemento: 'main h1',
            titulo: 'Cupom',
            texto: 'Código, desconto (% ou fixo), validade, limites e os cursos em que vale.',
        },
    ],
    // Relatórios.
    'relatorios/ver': [
        {
            elemento: 'main h1',
            titulo: 'Relatório',
            texto: 'Filtre o período em "De" e "Até". Exporte em CSV ou imprima em PDF.',
        },
        {
            elemento: '[data-tour="filtros-relatorio"]',
            titulo: 'Filtros',
            texto: 'Cada campo filtra por um valor (equipe, modalidade, área, situação, instrutor...). A busca procura em todas as colunas.',
        },
        {
            elemento: '[data-tour="modo-relatorio"]',
            titulo: 'Analítico ou sintético',
            texto: 'Analítico: cada linha. Sintético: somado pelo campo escolhido (ex.: receita por equipe). O CSV e o PDF levam o que está na tela.',
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
