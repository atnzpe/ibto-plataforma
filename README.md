# IBTO — Plataforma de cursos online

Plataforma de cursos online do IBTO (Instituto Brasileiro de Treinamento Operacional): **Laravel 13 + React + MySQL**, hospedada na HostGator.

- Endereço: `cursos.ib-to.org` (testes em `cursos-homolog.ib-to.org`)
- Planejamento e issues: épico [#33](https://github.com/atnzpe/Instituto-Brasileiro-de-Treinamento-Operacional/issues/33) no repositório do site, com a ordem no [ROADMAP](https://github.com/atnzpe/Instituto-Brasileiro-de-Treinamento-Operacional/blob/main/ROADMAP.md)
- Fluxo de trabalho: o mesmo do site (issue → branch → commits → testes → PR → revisão → merge). Nada de commit direto na `main`.

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

`GET /api/saude` responde `200 {"status":"ok","banco":"ok"}` ou `503 {"status":"erro","codigo":"SAUDE_BANCO_INDISPONIVEL"}`. É pública, então nunca expõe detalhes internos; o código de erro é estável e aparece também no log.

## Indexação

Enquanto `APP_INDEXAVEL=false` (o padrão), todas as páginas saem com `noindex, nofollow`. Só ligar em produção, no lançamento.
