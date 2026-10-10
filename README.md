# IBTO — Plataforma de cursos online

Plataforma de cursos online do IBTO (Instituto Brasileiro de Treinamento Operacional): **Laravel 13 + React + MySQL**, hospedada na HostGator.

- Endereço: `cursos.ib-to.org` (testes em `cursos-homolog.ib-to.org`)
- Planejamento e issues: épico [#33](https://github.com/atnzpe/Instituto-Brasileiro-de-Treinamento-Operacional/issues/33) no repositório do site, com a ordem no [ROADMAP](https://github.com/atnzpe/Instituto-Brasileiro-de-Treinamento-Operacional/blob/main/ROADMAP.md)
- Fluxo de trabalho: o mesmo do site (issue → branch → commits → testes → PR → revisão → merge). Nada de commit direto na `main`.

## Visão do produto

O IBTO tem três partes, cada uma com o seu público (Gleyson, 10/10/2026):

| Parte                                         | Endereço           | Para quem                     | O que tem                                                                                                                                                                                                               |
| --------------------------------------------- | ------------------ | ----------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Landpage** (outro repositório)              | `ib-to.org`        | Público, sem login            | Site institucional; a página `/cursos` mostra os cursos abertos ao público vindos desta plataforma                                                                                                                      |
| **Plataforma de cursos** (este repositório)   | `cursos.ib-to.org` | Com login, em três visões     | **Aluno:** catálogo, cursos e trilhas, aulas, provas, certificado e dossiê. **Instrutor:** cria cursos na sua área, com revisão do IBTO, e cuida das turmas. **Admin de equipe:** filiados e anuidade da própria equipe |
| **Painel "Comando Geral"** (este repositório) | `cursos.ib-to.org` | Equipe do IBTO, por permissão | Cadastros, usuários e permissões, financeiro, anuidade, histórico de tudo e o painel de indicadores                                                                                                                     |

## O que já existe

| Módulo                                                                                                                                                                                                | Situação               | Issue     |
| ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------- | --------- |
| Base, publicação sem SSH e saúde do sistema                                                                                                                                                           | ✅ no ar (homologação) | #35       |
| Três idiomas pelo Google Tradutor                                                                                                                                                                     | ✅ no ar               | #53       |
| Login com duas travas de tentativas, Aluno automático no cadastro, papéis e uma permissão por tela, com auditoria                                                                                     | ✅ no ar               | #36       |
| Nenhum erro em silêncio (403, 500 e erros das telas no log, com código)                                                                                                                               | ✅ no ar               | #58       |
| Cadastros: áreas de atuação, níveis, equipes, filiados (dados pessoais criptografados), convite, importação da planilha                                                                               | ✅ no ar               | #37       |
| **Financeiro:** livro-caixa (a receber e a pagar), edição só do pendente, baixa total ou parcial, parcelas, cancelamento e estorno com motivo, histórico só leitura, categorias e formas de pagamento | 🚧 PR #11 (rascunho)   | #59       |
| **Anuidade:** valor por ano (2026 isento, R$ 90,00 em 2027); o pagamento gera a receita no livro-caixa; estorno desfaz o "em dia"                                                                     | 🚧 PR #11 (rascunho)   | #37 e #59 |
| Painel "Comando Geral" com indicadores (desenho aprovado em 09/10/2026)                                                                                                                               | ⏳ próximo             | #59 e #45 |
| Visões do aluno e do instrutor (catálogo, cursos, trilhas, matrícula, provas, certificado)                                                                                                            | ⏳ depois              | #38 a #44 |

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

## Rota de saúde

`GET /api/saude` (no máximo 30 consultas por minuto por IP) responde `200 {"status":"ok","banco":"ok"}` ou `503 {"status":"erro","codigo":"SAUDE_BANCO_INDISPONIVEL"}`. É pública, então nunca expõe detalhes internos; o código de erro é estável e aparece também no log.

## Indexação

Enquanto `APP_INDEXAVEL=false` (o padrão), todas as páginas saem com `noindex, nofollow`. Só ligar em produção, no lançamento.

## Publicação (homologação)

Todo merge na `main` roda a CI e publica em `cursos-homolog.ib-to.org` por FTPS (`.github/workflows/deploy-homologacao.yml`). Sem SSH: migrations e caches rodam pelo Cron Job (`plataforma:pos-deploy`). Configuração inicial e problemas comuns em [docs/publicacao-homologacao.md](./docs/publicacao-homologacao.md). A produção ainda não existe.
