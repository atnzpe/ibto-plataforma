# Histórico de versões (CHANGELOG)

Todas as mudanças que importam para quem usa a plataforma ficam aqui, da mais nova para a mais antiga.
O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e as versões seguem o [Versionamento Semântico](https://semver.org/lang/pt-BR/):

- **MAIOR** (1.0.0): mudança que quebra algo para quem já usa (ex.: a produção no ar com uma regra nova de dados).
- **MENOR** (0.**2**.0): funcionalidade nova, sem quebrar nada.
- **CORREÇÃO** (0.1.**1**): conserto, sem funcionalidade nova.

Enquanto a versão começar com **0**, a plataforma está em homologação (antes do piloto e da produção).

## [Não lançado]

### Adicionado

- Blog e eventos mostrados no site `ib-to.org`: API pública do post inteiro e da galeria, paginação e filtro por categoria; as páginas `/blog` e `/eventos` da plataforma levam ao site (#61, PR #33).
- CORS das imagens para o modo offline do site; `robots.txt` e `llms.txt` da plataforma (#61, PR #33).
- Tour guiado nas telas principais (#68): abre sozinho na primeira visita de cada tela, botão "?" para rever, interruptor "Tour guiado" no menu da conta (salvo na conta), textos em português, teclado e leitor de tela.
- Sala ao vivo nos encontros online (#71): campo "Link da sala ao vivo" na aula (só Google Meet, Zoom, Teams ou YouTube, por https); o aluno confirmado vê "Entrar na sala" de 30 minutos antes até o fim do encontro; quem organiza vê sempre; o link nunca sai no catálogo nem na API.
- Página Ajuda com o manual de instruções de cada perfil (#68): aluno e filiado, instrutor, admin de equipe, Comando Geral e Tecnologia; cada seção com passos, regras e o porquê, busca, "Mostrar na tela" (abre a tela com o tour) e impressão ou PDF. Administrador e Tecnologia veem todos os manuais.

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
