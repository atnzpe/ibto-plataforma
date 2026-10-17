/**
 * MANUAL DE INSTRUÇÕES (#68, parte 2): o conteúdo da página Ajuda, um manual
 * para cada perfil, em português simples.
 *
 * Cada seção diz PARA QUE SERVE, os PASSOS e as REGRAS com o porquê, e pode
 * ter o botão "Mostrar na tela", que abre a tela já com o tour guiado.
 * Quem vê o quê é decidido no servidor (AjudaController): cada pessoa vê o
 * manual do seu papel; Administrador e Tecnologia veem todos (transparência).
 *
 * Regra da casa: toda issue que muda uma tela atualiza a seção dela aqui.
 */

/** Uma seção do manual (uma tarefa). */
export type SecaoDoManual = {
    /** Identificador curto, para o link direto. Ex.: 'garantir-vaga' */
    id: string;
    titulo: string;
    /** Para que serve, em uma ou duas frases. */
    resumo: string;
    /** O passo a passo, um item por ação. */
    passos: string[];
    /** As regras que valem, cada uma com o porquê. */
    regras?: string[];
    /** A tela da tarefa: o endereço e o nome mostrado no botão "Mostrar na tela". */
    tela?: { endereco: string; nome: string };
};

/** Um manual: o de um perfil. */
export type Manual = {
    /** Ex.: 'aluno' */
    id: IdDoManual;
    titulo: string;
    /** Para quem é. */
    publico: string;
    secoes: SecaoDoManual[];
};

/** Os manuais existentes. */
export type IdDoManual =
    | 'aluno'
    | 'instrutor'
    | 'admin-equipe'
    | 'comando-geral'
    | 'tecnologia';

export const MANUAIS: Manual[] = [
    {
        id: 'aluno',
        titulo: 'Aluno e filiado',
        publico:
            'Quem faz cursos no IBTO: qualquer pessoa cadastrada e os filiados.',
        secoes: [
            {
                id: 'entrar',
                titulo: 'Pedir acesso e entrar',
                resumo: 'O IBTO é um grupo fechado, só de indicados: todo acesso novo passa por uma análise.',
                passos: [
                    'Recebeu um convite por e-mail? Clique no link e crie a sua senha: a conta já está aprovada.',
                    'Foi indicado e não tem convite? Na tela de entrar, clique em "Pedir acesso" e informe nome, e-mail, a sua equipe, quem indicou você e a senha.',
                    'Confirme o e-mail pelo link que chega (veja também o spam).',
                    'Aguarde a análise do admin da sua equipe ou do IBTO; você recebe um e-mail com a resposta.',
                    'Aprovado, entre com o e-mail e a senha. A primeira tela é "Meus cursos".',
                    'Esqueceu a senha? Na tela de entrar, use "Esqueci a senha".',
                ],
                regras: [
                    'Enquanto o pedido está em análise, a plataforma só mostra a tela "Cadastro em análise".',
                    'Um e-mail só pode ter uma conta.',
                    'Depois de várias senhas erradas seguidas, o login espera alguns minutos: isso protege a sua conta contra quem tenta adivinhar a senha.',
                ],
            },
            {
                id: 'catalogo',
                titulo: 'Encontrar um curso',
                resumo: 'O catálogo mostra os cursos publicados, presenciais e online.',
                passos: [
                    'Abra "Catálogo de cursos" no menu.',
                    'Use os filtros por área e modalidade, ou a busca pelo nome.',
                    'Clique no curso para ver datas, local, valor, instrutor e o cronograma dos encontros.',
                    'No cronograma, clique num encontro para ver os detalhes e salvar o lembrete na sua agenda.',
                ],
                regras: [
                    'Curso restrito (marcado no catálogo) é só para filiado ativo: a inscrição confere o seu cadastro de filiado.',
                    'A Formação de Instrutores não aparece no catálogo: a matrícula nela é feita pelo IBTO.',
                ],
                tela: { endereco: '/cursos', nome: 'Catálogo de cursos' },
            },
            {
                id: 'garantir-vaga',
                titulo: 'Garantir a vaga e pagar',
                resumo: 'A inscrição reserva a vaga; o pagamento por PIX a confirma.',
                passos: [
                    'Na página do curso, confira o quadro "Requisitos" (se houver): cada item mostra ✅ (cumprido) ou ❌ (falta). Faltando algum, o botão fica "Faltam pré-requisitos".',
                    'Na página do curso, clique em "Garantir minha vaga".',
                    'Aparece o quadro "Pague com Pix": aponte a câmera do app do banco para o QR code, ou toque em "Copiar código Pix" e cole em "Pix copia e cola" no app. O valor já vai preenchido.',
                    'Antes de confirmar no app do banco, confira se quem recebe é o Instituto Brasileiro de Treinamento Operacional (ou o favorecido mostrado na tela). Outro nome? Não pague e avise o IBTO pelo suporte.',
                    'Envie o comprovante (foto ou PDF) na mesma tela.',
                    'Quando o IBTO confirmar o pagamento, a situação muda para "Confirmada" e chega um e-mail.',
                    'Curso gratuito: a matrícula é confirmada na hora.',
                ],
                regras: [
                    'A vaga fica reservada enquanto o pagamento é conferido: assim ninguém perde a vaga por esperar a confirmação.',
                    'Desistiu antes da confirmação do pagamento? Use "Desistir" na mesma tela: a vaga volta para a turma e a matrícula é cancelada, nunca apagada (fica o histórico).',
                ],
            },
            {
                id: 'meus-cursos',
                titulo: 'Assistir às aulas e entrar na sala ao vivo',
                resumo: '"Meus cursos" reúne os seus cursos, com o progresso de cada um.',
                passos: [
                    'Abra "Meus cursos" e clique em "Começar" (ou "Continuar") no curso.',
                    'Assista à aula e clique em "Marcar como assistida": a barra de progresso anda.',
                    'Use "Próxima" e "Anterior" para seguir a sequência.',
                    'Encontro online ao vivo: o botão "Entrar na sala" aparece 30 minutos antes do início e some no fim do encontro.',
                ],
                regras: [
                    'O link da sala ao vivo só aparece para matrícula confirmada e só na hora do encontro: assim ele não circula fora da turma.',
                ],
                tela: { endereco: '/meus-cursos', nome: 'Meus cursos' },
            },
            {
                id: 'certificado',
                titulo: 'Presença e certificado',
                resumo: 'O certificado depende da presença nos encontros e das aulas gravadas assistidas.',
                passos: [
                    'Curso com encontros: em cada encontro, o instrutor (ou o admin da equipe) faz a chamada; com pelo menos 75% de presença, o IBTO emite o certificado.',
                    'Curso gravado (só aulas online): marque cada aula como assistida; ao marcar a última, o certificado sai na hora, sozinho.',
                    'Curso com encontros e aulas gravadas: precisa dos 75% de presença e de todas as gravadas assistidas.',
                    'O certificado aparece no "Meu dossiê", com o botão "Ver".',
                ],
                regras: [
                    'Todo certificado tem um QR code: qualquer pessoa confere na hora, pela internet, se ele é verdadeiro.',
                    'A página de conferência mostra o nome abreviado: os seus dados não ficam expostos.',
                ],
            },
            {
                id: 'dossie',
                titulo: 'Meu dossiê',
                resumo: 'O seu histórico no IBTO num lugar só.',
                passos: [
                    'Abra "Meu dossiê" no menu.',
                    'Veja a graduação, os certificados, os cursos, as presenças e o que foi trabalhado em cada encontro.',
                ],
                tela: { endereco: '/meu-dossie', nome: 'Meu dossiê' },
            },
            {
                id: 'conta',
                titulo: 'Sua conta, idioma, tour e suporte',
                resumo: 'Ajustes da conta e onde pedir ajuda.',
                passos: [
                    'Clique no seu nome (embaixo, à esquerda) para abrir o menu da conta: Configurações, Tour guiado e Sair.',
                    'Em Configurações, troque nome, e-mail e senha.',
                    'Em Configurações → Perfil, "Escolher foto" coloca a sua foto (JPG, PNG ou WebP, até 5 MB; ela é recortada em quadrado). Ela aparece no menu da conta e ajuda o instrutor a reconhecer você na chamada.',
                    'Se você é filiado, ali também ficam a foto 3x4 (a da carteirinha) e a caixa "Autorizo o uso da minha foto na Rede Nacional do site". A caixa começa desmarcada: sem ela, a foto nunca sai da plataforma.',
                    'O menu de idiomas (no topo) explica como traduzir a tela para inglês ou espanhol.',
                    'O "?" do topo abre o tour da tela; "Tour guiado: ligado/desligado" controla se ele abre sozinho.',
                    'Problema técnico? Use o botão "Suporte técnico": ele abre o WhatsApp com a mensagem pronta.',
                ],
            },
        ],
    },
    {
        id: 'instrutor',
        titulo: 'Instrutor',
        publico: 'Quem propõe e ministra cursos.',
        secoes: [
            {
                id: 'propor-curso',
                titulo: 'Propor um curso',
                resumo: 'O instrutor cadastra o curso; o IBTO confere e publica (a chancela).',
                passos: [
                    'Menu "Cursos" → "Novo curso".',
                    'Preencha como no cartaz: título, chamada, área, modalidade (presencial ou online), tipo de acesso, valor, vagas, datas, horário, local, carga horária, instrutor, material incluso, WhatsApp e a imagem.',
                    'Salve. O curso fica como rascunho até você enviar para a chancela.',
                ],
                regras: [
                    'A parte do IBTO no valor (padrão 20%) é definida pelo Comando Geral; o instrutor não vê esse campo.',
                    'A parte do instrutor (padrão 80%) vira uma conta a pagar no financeiro quando o pagamento é confirmado.',
                ],
                tela: { endereco: '/comando-geral/cursos', nome: 'Cursos' },
            },
            {
                id: 'conteudo',
                titulo: 'Montar o conteúdo: módulos, aulas e encontros',
                resumo: 'O conteúdo é organizado em módulos, e cada módulo em aulas.',
                passos: [
                    'No curso, abra "Conteúdo" e crie os módulos.',
                    'Em cada módulo, crie as aulas: vídeo (link do YouTube ou arquivo), texto e apostila em PDF.',
                    'Aula com dia e horário é um ENCONTRO (aparece no cronograma e tem chamada).',
                    'Encontro online ao vivo: preencha o "Link da sala ao vivo" (Google Meet, Zoom, Teams ou YouTube).',
                    'Quando estiver tudo pronto, clique em "Enviar para chancela".',
                ],
                regras: [
                    'O link da sala só aceita Meet, Zoom, Teams ou YouTube por https: um link qualquer poderia levar o aluno a um site falso.',
                    'Vídeo enviado tem limite de 200 MB; vídeos maiores vão pelo YouTube (não listado).',
                ],
            },
            {
                id: 'chamada',
                titulo: 'Inscritos, chamada e certificados',
                resumo: 'A chamada de cada encontro alimenta o dossiê do aluno e libera o certificado.',
                passos: [
                    'No curso, abra "Inscritos" → "Encontros e chamada" → "Fazer a chamada".',
                    'Marque quem veio e escreva "O que foi trabalhado no encontro" → "Salvar chamada".',
                    'Em "Certificados", veja quem está apto (75% de presença e, se houver, todas as aulas gravadas assistidas) e emita.',
                    'Curso só gravado: o certificado sai sozinho quando o aluno marca a última aula; não precisa emitir.',
                ],
                regras: [
                    'Na chamada e nos inscritos aparece a foto de quem colocou foto, para ajudar a reconhecer o aluno. Você só vê a foto dos alunos dos SEUS cursos.',
                    'Quem não for marcado leva falta: a chamada sempre registra todos os confirmados.',
                    'O texto "o que foi trabalhado" aparece no dossiê de cada aluno presente.',
                ],
            },
        ],
    },
    {
        id: 'admin-equipe',
        titulo: 'Administrador de equipe',
        publico: 'O responsável por uma equipe filiada.',
        secoes: [
            {
                id: 'filiados-equipe',
                titulo: 'Filiados da equipe',
                resumo: 'O admin de equipe cuida dos filiados da própria equipe.',
                passos: [
                    'Menu "Filiados": a lista mostra só a sua equipe.',
                    'Use "Novo filiado" para cadastrar e "Convite" para a pessoa criar a senha.',
                    'Na ficha do filiado, preencha a "Data de filiação ao IBTO" (a real, não a do cadastro): ela conta o tempo de filiado nos cursos com pré-requisito.',
                    'Na ficha do filiado, "Escolher foto 3x4" coloca a foto da carteirinha. Só marque "O filiado autorizou o uso da foto na Rede Nacional" com a autorização assinada em mãos.',
                ],
                regras: [
                    'Cada equipe tem um limite de filiados definido pelo Comando Geral.',
                    'Fotos ficam em pasta privada: só a própria pessoa, o admin da equipe dela, o Comando Geral e os instrutores dos cursos dela veem. Marcar ou desmarcar a autorização de imagem fica na auditoria, com quem fez.',
                    'CPF, nascimento, endereço e telefone ficam criptografados no banco e nunca aparecem em listas nem relatórios (LGPD).',
                ],
                tela: { endereco: '/cadastros/filiados', nome: 'Filiados' },
            },
            {
                id: 'solicitacoes-equipe',
                titulo: 'Aprovar pedidos de acesso da equipe',
                resumo: 'Quem pede acesso escolhe a equipe; o admin daquela equipe confere a indicação e decide.',
                passos: [
                    'Menu "Solicitações de cadastro": aparecem só os pedidos para a sua equipe.',
                    'Confira o nome, o e-mail (se já foi confirmado) e quem indicou.',
                    'Clique em "Aprovar" (a pessoa vira Aluno e recebe um e-mail) ou em "Recusar" e escreva o motivo (vai no e-mail).',
                ],
                regras: [
                    'O IBTO é um grupo fechado: só aprove quem foi mesmo indicado.',
                    'Toda aprovação e recusa fica na auditoria (quem, quando e o motivo).',
                ],
                tela: {
                    endereco: '/admin/solicitacoes',
                    nome: 'Solicitações de cadastro',
                },
            },
            {
                id: 'chamada-equipe',
                titulo: 'Chamada dos cursos da equipe',
                resumo: 'O admin de equipe também pode fazer a chamada dos cursos da equipe.',
                passos: [
                    'Abra o curso da equipe → "Inscritos" → "Encontros e chamada".',
                    'Marque os presentes, escreva o que foi trabalhado e salve.',
                ],
            },
        ],
    },
    {
        id: 'comando-geral',
        titulo: 'Comando Geral (Administrador)',
        publico:
            'A diretoria e a equipe do IBTO. Veja também todos os outros manuais.',
        secoes: [
            {
                id: 'painel',
                titulo: 'O painel do Comando Geral',
                resumo: 'Os números da plataforma e o atalho para cada tarefa.',
                passos: [
                    'Abra "Comando Geral" no menu.',
                    'Clique em um cartão para abrir a tela dele.',
                ],
                tela: { endereco: '/comando-geral', nome: 'Comando Geral' },
            },
            {
                id: 'chancela',
                titulo: 'Chancelar e publicar cursos',
                resumo: 'Nenhum curso vai ao ar sem a aprovação do IBTO.',
                passos: [
                    '"Cursos" → "Ver os em análise" → abra o curso.',
                    'Confira os dados e a "Parte do IBTO (%)" (padrão 20; Formação de Instrutores: 100).',
                    'Clique em "Chancelar e publicar", ou devolva com o motivo.',
                ],
                regras: [
                    'Curso fechado (ex.: Formação de Instrutores) não aparece no site nem no catálogo: só o IBTO matricula.',
                ],
                tela: { endereco: '/comando-geral/cursos', nome: 'Cursos' },
            },
            {
                id: 'matriculas',
                titulo: 'Matrículas e pagamentos PIX',
                resumo: 'O Comando Geral confere o comprovante e confirma o pagamento.',
                passos: [
                    '"Matrículas" → filtre por "Aguardando confirmação".',
                    'Pré-requisitos: no cadastro do curso, a seção "Pré-requisitos" define os cursos anteriores (basta ter concluído um), o nível mínimo e o tempo mínimo de filiado. Só o IBTO define.',
                    'Exceção: em "Inscritos" → "Matricular pessoa", quem não cumpre os requisitos só entra com o campo "Motivo" preenchido; o motivo fica na matrícula e a exceção vai para a auditoria.',
                    'Cada matrícula mostra o identificador do Pix (ex.: "Pix: IBTOMAT1234"). Ele vem no código que o aluno pagou e aparece no extrato do banco: procure por ele para achar o pagamento certo.',
                    'Abra a ficha, confira o comprovante com o extrato e clique em "Confirmar pagamento".',
                    'Pagamento errado? "Cancelar" com o motivo.',
                ],
                regras: [
                    'A confirmação lança a receita inteira no livro-caixa e a parte do instrutor como conta a pagar, na mesma operação: o financeiro nunca fica pela metade.',
                ],
                tela: {
                    endereco: '/comando-geral/matriculas',
                    nome: 'Matrículas',
                },
            },
            {
                id: 'financeiro',
                titulo: 'Financeiro (livro-caixa)',
                resumo: 'Receitas e despesas do IBTO, a receber e a pagar.',
                passos: [
                    '"Financeiro" → "Lançamentos": filtre por período (De/Até), tipo e situação.',
                    'Novo lançamento: descrição, valor, vencimento, categoria e forma; pode ser parcelado.',
                    'Recebeu ou pagou? "Dar baixa" (total ou parcial).',
                    'Errou um lançamento pago? "Estornar" com o motivo.',
                ],
                regras: [
                    'Valores são guardados em centavos: nada de erro de arredondamento.',
                    'Lançamento pago nunca muda nem some; corrige-se com estorno, para o histórico contar a verdade.',
                    'Toda movimentação vai para a auditoria (quem, quando, antes e depois).',
                ],
                tela: {
                    endereco: '/financeiro/lancamentos',
                    nome: 'Livro-caixa',
                },
            },
            {
                id: 'anuidade',
                titulo: 'Anuidade dos filiados',
                resumo: 'O valor por ano e a baixa do pagamento de cada filiado.',
                passos: [
                    'Na ficha do filiado, registre o pagamento da anuidade do ano.',
                ],
                regras: [
                    '2026 é isento; em 2027 a anuidade é de R$ 90,00 (ano civil).',
                    'O pagamento gera a receita no livro-caixa; o estorno desfaz o "em dia".',
                ],
            },
            {
                id: 'cadastros',
                titulo: 'Cadastros: áreas, níveis, equipes e filiados',
                resumo: 'A base de tudo: quem é quem na rede do IBTO.',
                passos: [
                    'Áreas de atuação e níveis: mantenha a lista oficial (o site mostra as áreas ativas).',
                    'Equipes: cidade, UF, responsável, telefone, logo e pelo menos uma área (sem área, a equipe não fica ativa).',
                    'Filiados: cadastro um a um ou importação da planilha (com prévia antes de gravar).',
                ],
                tela: { endereco: '/cadastros/equipes', nome: 'Equipes' },
            },
            {
                id: 'usuarios',
                titulo: 'Usuários e papéis',
                resumo: 'Quem pode fazer o quê na plataforma.',
                passos: [
                    '"Usuários" → procure a pessoa → "Papéis" → marque o papel → salvar.',
                    'Também: mandar convite, gerar nova senha, desativar e reativar a conta.',
                    'A pessoa precisa sair e entrar de novo para o menu mudar.',
                ],
                regras: [
                    'Administrador e Tecnologia têm as mesmas permissões, menos a grade de permissões e dar ou tirar o papel Tecnologia (só a Tecnologia).',
                    'Conta desativada não entra, mas nada é apagado: o histórico fica.',
                ],
                tela: { endereco: '/admin/usuarios', nome: 'Usuários' },
            },
            {
                id: 'solicitacoes',
                titulo: 'Pedidos de acesso (grupo fechado)',
                resumo: 'Contas novas entram por convite ou por pedido de acesso analisado.',
                passos: [
                    'Convite: em "Usuários" ou "Filiados", cadastre a pessoa e use "Convite"; a conta já nasce aprovada.',
                    'Pedido de acesso: menu "Solicitações de cadastro" mostra todos os pedidos em análise, do mais antigo para o mais novo.',
                    'Aprovar dá o papel Aluno e manda o e-mail; recusar pede o motivo, que vai no e-mail.',
                ],
                regras: [
                    'O admin de cada equipe analisa os pedidos da própria equipe; o Comando Geral e a Tecnologia veem todos.',
                    'A trava fica no servidor: quem está em análise não abre nenhuma outra tela, nem pelo endereço.',
                ],
                tela: {
                    endereco: '/admin/solicitacoes',
                    nome: 'Solicitações de cadastro',
                },
            },
            {
                id: 'blog',
                titulo: 'Blog e galeria de eventos',
                resumo: 'O blog aparece no site para todos; as fotos dos eventos, só para filiados.',
                passos: [
                    '"Blog" → "Novo post": título, categoria (Notícia ou Evento), resumo, texto, capa e vídeo.',
                    'Publique na hora ou agende uma data.',
                    '"Galeria" → "Novo álbum": envie as fotos, descreva cada uma e marque a autorização de imagem.',
                ],
                regras: [
                    'Post ou álbum com pessoas só publica com a autorização de imagem (LGPD); com menores, também a dos responsáveis.',
                    'Cada foto precisa de descrição: ela é lida pelos leitores de tela e ajuda o Google.',
                    'As fotos dos eventos são PRIVADAS: só filiados e cargos acima as veem, com login. Muitos alunos e instrutores são operadores de segurança, e a foto com rosto ou distintivo na internet pode pôr essas pessoas em risco. No site público aparecem só o nome, a data e o local do evento.',
                    'O blog é público: use fotos em que ninguém seja identificável (de costas, à distância ou desfocadas), ou com a autorização escrita.',
                ],
                tela: { endereco: '/comando-geral/blog', nome: 'Blog' },
            },
            {
                id: 'pagamento-config',
                titulo: 'Dados para pagamento',
                resumo: 'A chave PIX do IBTO e a de cada curso.',
                passos: [
                    '"Dados para pagamento": confira a chave PIX do IBTO e, se for o caso, a do curso.',
                ],
                regras: [
                    'Toda troca de chave pede a senha de novo, fica na auditoria e manda um aviso por e-mail aos responsáveis: protege contra golpe de troca de chave.',
                ],
                tela: {
                    endereco: '/comando-geral/pagamento',
                    nome: 'Dados para pagamento',
                },
            },
        ],
    },
    {
        id: 'tecnologia',
        titulo: 'Tecnologia',
        publico:
            'O time técnico (e a diretoria, para saber como tudo funciona por dentro).',
        secoes: [
            {
                id: 'permissoes',
                titulo: 'Papéis e permissões',
                resumo: 'A grade que diz o que cada papel pode fazer, tela por tela.',
                passos: [
                    '"Permissões": marque ou desmarque a permissão de cada papel e salve.',
                ],
                regras: [
                    'Uma permissão por tela: o menu esconde o que a pessoa não pode, e o servidor recusa (403) mesmo com o link.',
                    'Só a Tecnologia mexe na grade e no papel Tecnologia. A chave ADMIN_EMAILS do servidor é a chave de emergência.',
                ],
                tela: { endereco: '/admin/permissoes', nome: 'Permissões' },
            },
            {
                id: 'servidor',
                titulo: 'Configuração do servidor (.env)',
                resumo: 'Senhas e chaves ficam no arquivo .env do servidor, nunca no GitHub.',
                passos: [
                    'cPanel → Gerenciador de Arquivos → pasta da plataforma → .env (mostrar arquivos ocultos).',
                    'Chaves usadas: ADMIN_EMAILS (chave de partida: só numa instalação nova ou para recuperar o acesso; depois, deixe vazio), IBTO_PIX_CIDADE (a cidade que vai dentro do código Pix; a chave e o favorecido ficam na tela "Dados para pagamento", com senha e auditoria), SUPORTE_WHATSAPP, SITE_URL, CORS_ORIGENS, APP_INDEXAVEL, CADASTRO_PUBLICO, GALERIA_PUBLICA (false, o padrão: fotos dos eventos só para filiados; true: abertas a todos) e PILOTO_CARREGAR (true carrega UMA vez os cursos reais e as turmas [TESTE] do piloto; depois, false).',
                    'CADASTRO_PUBLICO=fechado (padrão): só convite; "Pedir acesso" só aparece enquanto ADMIN_EMAILS estiver preenchido, e só para aqueles e-mails. CADASTRO_PUBLICO=aprovacao: "Pedir acesso" para todos, com análise.',
                    'Depois de editar, apague bootstrap/cache/config.php (o Cron recria).',
                ],
                regras: [
                    'APP_INDEXAVEL fica false até o lançamento: o Google não indexa a plataforma antes da hora.',
                ],
            },
            {
                id: 'publicacao',
                titulo: 'Publicação, versões e voltar atrás',
                resumo: 'Como uma mudança chega ao ar.',
                passos: [
                    'Toda mudança: issue → branch → testes → PR → merge na main.',
                    'O merge publica sozinho na homologação (FTPS); o Cron roda as migrations e os caches.',
                    'Cada versão tem número (ex.: v0.1.0), CHANGELOG e release no GitHub.',
                ],
                regras: [
                    'A produção só recebe versões com número: voltar atrás é publicar a versão anterior.',
                ],
            },
            {
                id: 'erros',
                titulo: 'Erros, saúde e dados pessoais',
                resumo: 'Nenhum erro acontece em silêncio.',
                passos: [
                    'Erros do servidor e das telas vão para o log com um código estável (ex.: SAUDE_BANCO_INDISPONIVEL).',
                    'A rota /api/saude mostra se o sistema e o banco estão bem.',
                ],
                regras: [
                    'Dados pessoais (CPF, nascimento, endereço, telefone) nunca vão para o log, para testes ou para issues (LGPD).',
                ],
            },
        ],
    },
];

/**
 * Os manuais que uma pessoa vê, na ordem da página.
 *
 * @param permitidos Os manuais liberados pelo servidor. Ex.: ['aluno', 'instrutor']
 * @returns Só os manuais liberados.
 */
export function manuaisVisiveis(permitidos: IdDoManual[]): Manual[] {
    return MANUAIS.filter((manual) => permitidos.includes(manual.id));
}

/**
 * Filtra as seções pelo texto da busca (sem diferença de acento e maiúscula).
 *
 * @param manual O manual. Ex.: o do aluno.
 * @param busca O que a pessoa digitou. Ex.: 'certificado'
 * @returns As seções que têm o texto no título, no resumo, nos passos ou nas regras.
 */
export function secoesDaBusca(manual: Manual, busca: string): SecaoDoManual[] {
    const termo = normalizar(busca.trim());
    // Busca vazia: todas as seções.
    if (termo === '') {
        return manual.secoes;
    }

    return manual.secoes.filter((secao) =>
        normalizar(
            [
                secao.titulo,
                secao.resumo,
                ...secao.passos,
                ...(secao.regras ?? []),
            ].join(' '),
        ).includes(termo),
    );
}

/**
 * Deixa o texto comparável: sem acento e em minúsculas. Ex.: 'Matrícula' → 'matricula'.
 */
function normalizar(texto: string): string {
    return texto
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();
}
