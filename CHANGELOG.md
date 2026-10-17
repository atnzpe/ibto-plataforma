# Histórico de versões (CHANGELOG)

Todas as mudanças que importam para quem usa a plataforma ficam aqui, da mais nova para a mais antiga.
O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e as versões seguem o [Versionamento Semântico](https://semver.org/lang/pt-BR/):

- **MAIOR** (1.0.0): mudança que quebra algo para quem já usa (ex.: a produção no ar com uma regra nova de dados).
- **MENOR** (0.**2**.0): funcionalidade nova, sem quebrar nada.
- **CORREÇÃO** (0.1.**1**): conserto, sem funcionalidade nova.

Enquanto a versão começar com **0**, a plataforma está em homologação (antes do piloto e da produção).

## [Não lançado]

### Adicionado

- Cupom promocional nos cursos (#89): tela "Cupons" (Financeiro) para criar códigos com desconto percentual ou fixo, validade de/até, limite total e por pessoa, só filiados e cursos em que vale; o aluno digita na inscrição e o Pix já sai com o valor descontado (100% confirma na hora); recusas com código (`CUPOM_INVALIDO`, `CUPOM_VENCIDO`, `CUPOM_ESGOTADO`…); limite à prova de corrida; desconto antes da divisão IBTO/instrutor; permissão `cupons.gerenciar` (Comando Geral e Tecnologia).
- Pré-requisitos dos cursos (#88): curso anterior (basta um concluído, com certificado válido), nível mínimo e tempo mínimo de filiado; a página do curso mostra cada item com ✅ ou ❌ e bloqueia a inscrição de quem não cumpre (o servidor também recusa, `MATRICULA_REQUISITO_PENDENTE`); o Comando Geral matricula por exceção só com motivo, gravado na matrícula e auditado; campo "Data de filiação ao IBTO" na ficha do filiado.
- Base do piloto (`plataforma:carregar-piloto`): os cursos reais dos cartazes com a data real (Encerrado ou Rascunho), incluindo Operação Avançada e as três turmas do Treinamento de Combate Real em Piracicaba-SP, e o cartaz como capa, uma turma [TESTE] publicada de cada, 2 cursos online [TESTE] (gravado e ao vivo) e a trilha "Formação Operacional"; roda no servidor uma vez com `PILOTO_CARREGAR=true`, sem duplicar e sem criar contas.
- Pix copia e cola com QR code (#83): na inscrição, o aluno vê o QR code e o botão "Copiar código Pix", com o valor exato e o identificador da matrícula (ex.: `IBTOMAT1234`) já dentro do código, gerado na própria plataforma pelo padrão BR Code do Banco Central (sem banco nem contrato); aviso para conferir o nome de quem recebe no app do banco; o identificador aparece na lista e na ficha de matrículas do Comando Geral para conferir no extrato.
- Foto de perfil e foto 3x4 do filiado (#78): enviadas em Configurações → Perfil ou na ficha do filiado; recortadas em quadrado, reduzidas para 400 px e gravadas em WebP numa pasta privada; aparecem no menu da conta, nos inscritos e na chamada só para quem pode ver; autorização de imagem para a Rede Nacional, desmarcada por padrão, com data e auditoria.
- Blog e eventos mostrados no site `ib-to.org`: API pública do post inteiro e da galeria, paginação e filtro por categoria; as páginas `/blog` e `/eventos` da plataforma levam ao site (#61, PR #33).
- CORS das imagens para o modo offline do site; `robots.txt` e `llms.txt` da plataforma (#61, PR #33).
- Tour guiado nas telas principais (#68): abre sozinho na primeira visita de cada tela, botão "?" para rever, interruptor "Tour guiado" no menu da conta (salvo na conta), textos em português, teclado e leitor de tela.
- Sala ao vivo nos encontros online (#71): campo "Link da sala ao vivo" na aula (só Google Meet, Zoom, Teams ou YouTube, por https); o aluno confirmado vê "Entrar na sala" de 30 minutos antes até o fim do encontro; quem organiza vê sempre; o link nunca sai no catálogo nem na API.
- Página Ajuda com o manual de instruções de cada perfil (#68): aluno e filiado, instrutor, admin de equipe, Comando Geral e Tecnologia; cada seção com passos, regras e o porquê, busca, "Mostrar na tela" (abre a tela com o tour) e impressão ou PDF. Administrador e Tecnologia veem todos os manuais.
- Certificado automático do curso gravado (#72): exige 100% das aulas gravadas assistidas e sai sozinho ao marcar a última (uma vez só, sem "emitido por", auditado); curso com encontros continua com 75% de presença e emissão pelo IBTO; curso misto exige as duas coisas.
- Cadastro com aprovação (#84; grupo fechado): "Criar conta" virou "Pedir acesso", com equipe e quem indicou; a conta nasce em análise (trava no servidor) e o admin da equipe ou o Comando Geral aprova (vira Aluno, e-mail) ou recusa com motivo (e-mail); CADASTRO_PUBLICO=fechado deixa só o convite.
- E-mails com a marca do IBTO: emblema e nome no topo, cores da plataforma, texto próprio na confirmação (endereço oficial e prazo do link) e, no rodapé de todos, o aviso de segurança contra golpes ("o IBTO nunca pede senha, códigos ou dados bancários"; confira o endereço antes de clicar).

### Alterado

- Fotos dos eventos privadas por padrão: só filiados e cargos acima as veem, com login (muitos alunos e instrutores são operadores de segurança); visitante vai para a tela de entrar, aluno sem filiação vê o aviso, e a API do site entrega o evento sem fotos, com o link para entrar. O blog continua público. `GALERIA_PUBLICA=true` volta ao modo aberto.
- O cadastro público começa **fechado** (só convite) por padrão; a tela "Pedir acesso" só aparece enquanto a chave de partida (`ADMIN_EMAILS`) estiver preenchida, e só aceita aqueles e-mails, que entram já aprovados e sem precisar de equipe.

### Segurança

- Com o endereço oficial em https, todo link gerado pela plataforma (e-mails, botões, QR codes) sai com https.

### Corrigido

- Carga do piloto: curso gratuito entra como "só filiados" (regra do IBTO); antes entrava aberto ao público.
- Curso cancelado some do site em até 1 minuto (a lista de cursos da API pública ficava 5 minutos guardada e o link levava a 404).
- Deploy da homologação sem erro 500 no meio da troca de versão: o deploy põe a placa "em atualização" (503) antes de enviar o código e o Cron a tira depois das migrations e dos caches; novo job "Diagnóstico homologação" mostra os erros do log do servidor, sem dados pessoais.
- Página Ajuda com o menu lateral e o topo duplicados: a tela desenhava o layout de novo; agora usa o layout do app, e um teste barra telas que importem o layout principal.

## [0.1.0] — 2026-10-15 (homologação)

Primeira versão completa para o piloto, no ar em `cursos-homolog.ib-to.org`.

### Adicionado

- API pública das áreas de atuação (#57, PR #32) e dos cursos (#52, PR #31) para o site.
- API pública do blog com CORS só para o site do IBTO (#61, PR #29).
- Galeria de eventos: álbuns com descrição, crédito e autorização de imagem (#61, PR #28).
- Botão "Suporte técnico" pelo WhatsApp com mensagem padronizada (PR #27).
- Blog: notícias e eventos com regras de publicação no Comando Geral e páginas públicas (#61, PR #25).
- Certificado de curso no modelo institucional do Mestre Eduardo (PR #23).
- Situação de cada aula, barra de vagas e resumo do dossiê (desenhos de 10/10, PR #22).
- Matrículas com PIX, certificados com QR code, curso fechado, divisão do valor, progresso das aulas e Meu dossiê (#41, #44, PR #18).
- Filiados: importar mesmo assim e telas sem rolagem lateral (#37, PR #17).
- Usuários: desativar e reativar, nova senha e convite (#36, PR #16).
- Identidade visual do IBTO: cores, logo e fontes (PR #15).
- Cursos, trilhas, conteúdo e chancela (#38, #39, #40, PR #13).
- Financeiro: livro-caixa, anuidade, parcelas, cancelamento e filtros por período (#59, #37, PRs #11 e #12).
- Login reforçado e papel Aluno automático no cadastro (#36, PR #10).
- Papéis e permissões no molde da CBSA (#36, PR #7).
- Filiados: cadastro, convite, limite por equipe e importação (#37, PR #8).
- Cadastros de áreas de atuação, níveis e equipes (#37, PR #5).
- Menu de idiomas pelo Google Tradutor (#53, PR #4).
- Publicação automática em `cursos-homolog.ib-to.org` (#35, PR #2).
- Base da plataforma: Laravel 13, React, rota de saúde e CI (#35, PR #1).

### Corrigido

- `/api/publico` e `/imagens` sem a senha da homologação, para o site de testes ler o blog (PR #30).
- Janelas que abrem por cima da tela não passam da área segura (PR #26); área segura em todas as telas (PR #14).
- Lista de cursos troca tabela por cartões em 1024 px (PR #24).
- Telas e mensagens em português; menu sem os links do kit (PRs #20 e #21).
- Nenhum erro em silêncio, no servidor e nas telas (#58, PR #9).
- `VERSAO_DEPLOY` gravado por último e log sem dados pessoais (PR #6).
- Pendências da homologação: pós-deploy, vendor em .zip, e-mail pela fila (PR #3).

[Não lançado]: https://github.com/atnzpe/ibto-plataforma/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/atnzpe/ibto-plataforma/releases/tag/v0.1.0
