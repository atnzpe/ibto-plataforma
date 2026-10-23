# IBTO — Plataforma de cursos online

Plataforma de cursos online do IBTO (Instituto Brasileiro de Treinamento Operacional): **Laravel 13 + React + MySQL**, hospedada na HostGator.

- Endereço: `cursos.ib-to.org` (testes em `cursos-homolog.ib-to.org`)
- Planejamento e issues: épico [#33](https://github.com/atnzpe/Instituto-Brasileiro-de-Treinamento-Operacional/issues/33) no repositório do site, com a ordem no [ROADMAP](https://github.com/atnzpe/Instituto-Brasileiro-de-Treinamento-Operacional/blob/main/ROADMAP.md)
- Fluxo de trabalho: o mesmo do site (issue → branch → commits → testes → PR → revisão → merge). Nada de commit direto na `main`.

## Visão do produto

O IBTO tem três partes, cada uma com o seu público (Gleyson, 10/10/2026):

| Parte                                         | Endereço           | Para quem                     | O que tem                                                                                                                                                                                                               |
| --------------------------------------------- | ------------------ | ----------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Landpage** (outro repositório)              | `ib-to.org`        | Público, sem login            | Site institucional, blog e eventos (vindos desta plataforma); o endereço `/cursos` leva ao catálogo desta plataforma                                                                                                    |
| **Plataforma de cursos** (este repositório)   | `cursos.ib-to.org` | Com login, em três visões     | **Aluno:** catálogo, cursos e trilhas, aulas, provas, certificado e dossiê. **Instrutor:** cria cursos na sua área, com revisão do IBTO, e cuida das turmas. **Admin de equipe:** filiados e anuidade da própria equipe |
| **Painel "Comando Geral"** (este repositório) | `cursos.ib-to.org` | Equipe do IBTO, por permissão | Cadastros, usuários e permissões, financeiro, anuidade, histórico de tudo e o painel de indicadores                                                                                                                     |

## O que já existe

Situação em 18/10/2026. Tudo abaixo está no ar na homologação (`cursos-homolog.ib-to.org`); a produção é um clone, depois do piloto (#56).

| Módulo                | O que faz                                                                                                                                                                                                                                                                                             | Issue         |
| --------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------- |
| Base e publicação     | Laravel 13 + React, publicação automática sem SSH, rota de saúde, nenhum erro em silêncio                                                                                                                                                                                                             | #35, #58      |
| Idiomas               | Português, inglês e espanhol pelo Google Tradutor                                                                                                                                                                                                                                                     | #53           |
| Login e permissões    | Duas travas de tentativas, cadastro com aprovação (grupo fechado: pedido de acesso analisado ou convite, #84), 5 papéis, uma permissão por tela, auditoria                                                                                                                                            | #36           |
| Usuários              | Desativar e reativar, nova senha, convite                                                                                                                                                                                                                                                             | #36           |
| Cadastros             | Áreas de atuação, níveis (nome, ordem e letra do certificado livres), equipes (com logo), filiados com dados pessoais criptografados, convite, importação da planilha                                                                                                                                 | #37           |
| Financeiro            | Livro-caixa a receber e a pagar, baixa total ou parcial, parcelas, cancelamento e estorno com motivo; cada lançamento ligado à pessoa, curso e programa; filtros robustos (papel, pessoa, equipe, curso, modalidade, código, valor, forma), cartões sobre o filtro, analítico ou sintético, CSV e PDF | #59           |
| Anuidade              | Valor por ano (2026 isento; R$ 90,00 em 2027); o pagamento gera a receita no livro-caixa; curso exclusivo exige a anuidade em dia                                                                                                                                                                     | #37, #59      |
| Cursos e trilhas      | Cadastro completo, presencial ou online, 3 tipos de acesso, curso fechado, chancela do IBTO, conteúdo em módulos e aulas, trilhas                                                                                                                                                                     | #38, #39, #40 |
| Matrícula e pagamento | Inscrição, PIX com comprovante ("aguardando conferência"; recusa com motivo sem cancelar), confirmação, trava de vagas, divisão do valor (20% IBTO, 80% instrutor), página /pagar/<código>                                                                                                            | #41           |
| Sala ao vivo          | Link da sala (Meet, Zoom, Teams ou YouTube) nos encontros online; botão "Entrar na sala" só na janela do encontro e só para matrícula confirmada                                                                                                                                                      | #71           |
| Chamada e certificado | Chamada por encontro com "o que foi trabalhado", certificado com 75% de presença (curso gravado: 100% das aulas, emitido sozinho, #72), modelo institucional, QR code e validação pública                                                                                                             | #44, #46      |
| Área do aluno         | Meus cursos com progresso, aula online com "marcar como assistida", Meu dossiê com o diário de aulas (o que foi ministrado em cada aula)                                                                                                                                                              | #43, #44      |
| Provas                | Múltipla escolha por curso, nota mínima e tentativas; gabarito só no servidor; o certificado exige a aprovação; o instrutor vê as notas                                                                                                                                                               | #43           |
| Aulas regulares       | Programas por assinatura (Krav Maga, Kapap...), mensalidade pelo Pix, carência de 5 dias, chamada com o conteúdo da aula, painel financeiro (previsto, recebido, a receber, atraso, repasse)                                                                                                          | #80           |
| Relatórios            | Filiados, cursos, frequência, financeiro e auditoria com filtros por dimensão, busca, analítico ou sintético, CSV e PDF; Meu painel com a saúde financeira do instrutor                                                                                                                               | #73, #79      |
| Segurança e qualidade | Cabeçalhos de segurança e CSP em toda resposta, auditoria axe de 15 telas na CI, Lighthouse depois de cada deploy, logs JSON com máscara LGPD, PWA offline, VLibras, SEO                                                                                                                              | #74           |
| Blog e galeria        | Notícias e eventos com regras de publicação e autorização de imagem; mostrados no site (`ib-to.org/blog` e `/eventos`)                                                                                                                                                                                | #61           |
| API pública           | Blog, eventos, cursos e áreas em JSON, só leitura, CORS só para o site, 60 pedidos por minuto                                                                                                                                                                                                         | #52, #57, #61 |
| Suporte               | Botão "Suporte técnico" pelo WhatsApp, com mensagem padronizada                                                                                                                                                                                                                                       | —             |
| Tour guiado           | Balões que explicam cada tela principal; abre sozinho na primeira visita, "?" para rever, liga/desliga no menu da conta                                                                                                                                                                               | #68           |
| Manual de instruções  | Página Ajuda com um manual por perfil (Administrador e Tecnologia veem todos), busca, "Mostrar na tela" e PDF                                                                                                                                                                                         | #68           |

Pendentes de decisão humana: texto da política de privacidade e o encarregado de dados (#47), modelo do certificado de graduação (#46, parte 2), planilha das equipes (#11), produção (#56) e open source (#85). Pagamento: Pix manual (decidido; gateway fica para depois do piloto).

Regras de dinheiro: valores em **centavos** (nada de vírgula no cálculo); lançamento pago **nunca** muda nem some (corrige-se com estorno); toda movimentação vai para a auditoria na mesma transação.

## Stack

| Parte      | Tecnologia                                                                                    |
| ---------- | --------------------------------------------------------------------------------------------- |
| Backend    | PHP 8.3 + Laravel 13 (Composer travado no PHP 8.3 do servidor)                                |
| Frontend   | Kit React oficial do Laravel: Inertia + React + TypeScript + Tailwind, com Vite+ (`vp`) e Bun |
| Banco      | MySQL (Percona Server 5.7.44 na HostGator); SQLite na máquina local                           |
| Testes     | Pest 4 (PHP) e `bun test` (front)                                                             |
| Hospedagem | HostGator, plano compartilhado: **sem SSH e sem Node** no servidor                            |

## Rodar na máquina

Requisitos: [Laravel Herd](https://herd.laravel.com) (PHP + Composer) e [Bun](https://bun.sh).

```bash
composer setup   # 1ª vez: instala tudo, cria o .env, a chave e o banco SQLite
composer dev     # servidor em http://localhost:8000 + Vite
```

A página inicial mostra a "Situação do sistema", lida de `/api/saude`.

## Primeira instalação (banco zerado)

A plataforma é de um **grupo fechado**: ninguém entra sem convite ou sem ter o pedido de acesso aprovado (#84). Por isso, numa instalação nova (banco vazio, sem ninguém da Tecnologia), é preciso uma **chave de partida**:

1. No `.env`, coloque o e-mail de quem vai cuidar da plataforma: `ADMIN_EMAILS=tecnologia@sua-instituicao.org` (aceita vários, separados por vírgula).
2. Abra a plataforma e use **Pedir acesso** com esse e-mail. Por estar na chave de partida, a conta **já nasce aprovada**.
3. Confirme o e-mail e entre. Em **Usuários**, dê a você mesmo o papel **Tecnologia**.
4. **Deixe o `ADMIN_EMAILS` vazio** (`ADMIN_EMAILS=`) e apague `bootstrap/cache/config.php`. A partir daqui, quem tem o papel Tecnologia dá os papéis, e as contas novas entram por convite ou por pedido aprovado.

O `ADMIN_EMAILS` é só para começar (ou para recuperar o acesso se ninguém da Tecnologia conseguir entrar). Deixá-lo preenchido depois disso é uma porta a mais aberta.

**Cadastro público:** `CADASTRO_PUBLICO=fechado` (**padrão**: só convite; a tela "Pedir acesso" só aparece enquanto o `ADMIN_EMAILS` estiver preenchido, e só aceita aqueles e-mails) ou `CADASTRO_PUBLICO=aprovacao` ("Pedir acesso" para todos, com análise do admin da equipe ou do Comando Geral).

## Base do piloto (cursos reais e turmas de teste)

O comando `php artisan plataforma:carregar-piloto` cadastra os cursos reais dos cartazes do IBTO e o que o piloto precisa para ser testado de ponta a ponta:

- **Cursos reais dos cartazes**, com a data real: Bastão Tático, Combate com Faca, Combate Corpo a Corpo, Operador de Arma de Fogo, Operação Avançada e as turmas de setembro e outubro do Treinamento de Combate Real (Piracicaba-SP) ficam **Encerrado** (histórico); Sobrevivência Urbana N1 (sem data) e a turma de 14 e 15/11 do Combate Real (sem valor no cartaz) ficam em **Rascunho**, para o Comando Geral completar e publicar. A capa é o cartaz.
- **1 turma [TESTE] de cada um dos 5 cursos principais**, publicada, com data futura, para os stakeholders se inscreverem e pagarem pelo Pix.
- **2 cursos online [TESTE]**: um gravado (certificado automático) e um encontro ao vivo.
- **Trilha "Formação Operacional"**: corpo a corpo → faca → arma de fogo → sobrevivência urbana.

Pode rodar mais de uma vez sem duplicar. Instrutores entram só como texto: nenhuma conta é criada.

**No servidor (sem SSH):** coloque `PILOTO_CARREGAR=true` no `.env` e apague `bootstrap/cache/config.php`. Em até 5 minutos o Cron carrega a base **uma vez** e grava a marca `storage/app/piloto-carregado` (aparece no job "Diagnóstico homologação"). Depois, volte para `PILOTO_CARREGAR=false`.

**Antes da produção:** as turmas com o título começando por `[TESTE]` são apagadas (com as matrículas de teste), e a trilha passa a apontar para as turmas reais.

## Comandos

| Comando                | O que faz                                                |
| ---------------------- | -------------------------------------------------------- |
| `composer ci:check`    | Tudo o que a CI roda (front e PHP)                       |
| `bun run build`        | Build de produção do front em `public/build`             |
| `bun run check`        | Lint e formatação do front (`bun run check:fix` corrige) |
| `bun run types:check`  | Checagem de tipos do TypeScript                          |
| `bun run test`         | Testes do front (`tests/js`)                             |
| `php artisan test`     | Testes do PHP (Pest)                                     |
| `composer lint`        | Formata o PHP (Pint)                                     |
| `composer types:check` | Análise do PHP (PHPStan)                                 |

A CI (`.github/workflows/ci.yml`) roda tudo isso a cada push, em PHP 8.3 e com um Percona Server 5.7.44, o mesmo banco da HostGator.

## API pública (somente leitura)

Usada pelo site `ib-to.org`. Sem login, só `GET`, 60 pedidos por minuto por IP, e o navegador só deixa o site do IBTO ler (`config/cors.php`, chave `CORS_ORIGENS`). Nenhum dado pessoal sai por aqui.

| Endereço                                        | O que devolve                                                |
| ----------------------------------------------- | ------------------------------------------------------------ |
| `/api/publico/blog?pagina=&categoria=&quantos=` | Posts no site, 12 por página no máximo                       |
| `/api/publico/blog/{slug}`                      | Um post inteiro; fora do site, 404 `POST_NAO_ENCONTRADO`     |
| `/api/publico/eventos` e `/{slug}`              | Álbuns publicados; fora do site, 404 `EVENTO_NAO_ENCONTRADO` |
| `/api/publico/cursos`                           | Cursos publicados e abertos                                  |
| `/api/publico/areas-atuacao`                    | Áreas de atuação ativas                                      |

As páginas públicas `/blog` e `/eventos` da plataforma levam ao site do mesmo ambiente. O endereço do site vem de `SITE_URL` ou é deduzido do `APP_URL` (`cursos-homolog.ib-to.org` → `homolog.ib-to.org`).

## SEO e agentes de IA

- `public/robots.txt`: libera catálogo, trilhas e validação; afasta os robôs das áreas com login e da API.
- `public/llms.txt`: explica a plataforma e a API pública para agentes de IA.

## Rota de saúde

`GET /api/saude` (no máximo 30 consultas por minuto por IP) responde `200 {"status":"ok","banco":"ok"}` ou `503 {"status":"erro","codigo":"SAUDE_BANCO_INDISPONIVEL"}`. É pública, então nunca expõe detalhes internos; o código de erro é estável e aparece também no log.

## Indexação

Enquanto `APP_INDEXAVEL=false` (o padrão), todas as páginas saem com `noindex, nofollow`. Só ligar em produção, no lançamento.

## Publicação (homologação)

Todo merge na `main` roda a CI e publica em `cursos-homolog.ib-to.org` por FTPS (`.github/workflows/deploy-homologacao.yml`). Sem SSH: migrations e caches rodam pelo Cron Job (`plataforma:pos-deploy`). Configuração inicial e problemas comuns em [docs/publicacao-homologacao.md](./docs/publicacao-homologacao.md). A produção ainda não existe.

**Versões e releases (#75):** versionamento semântico no arquivo `VERSION` (mostrado no rodapé e em `/api/saude`). Toda PR de código leva uma linha no `CHANGELOG.md` (a CI confere). Para lançar: mova o "Não lançado" para `## [X.Y.Z] — data`, troque o `VERSION`, faça a PR e o merge, e crie a tag `vX.Y.Z`; o workflow `release.yml` publica a release com o pacote da HostGator, o SHA-256 e as notas. A produção (#56) só recebe versões com tag.
