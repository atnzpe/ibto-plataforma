# CLAUDE.md — IBTO Plataforma de cursos

Como trabalhar neste repositório. O README explica o projeto. As regras gerais do IBTO (pessoas, idioma, fluxo, LGPD, economia de tokens) estão no [CLAUDE.md do site](https://github.com/atnzpe/Instituto-Brasileiro-de-Treinamento-Operacional/blob/main/CLAUDE.md) e valem aqui também; abaixo, só o que é próprio da plataforma.

## Onde estão as issues

No repositório do site, épico #33 (rótulo `plataforma`). Commits e PRs citam `atnzpe/Instituto-Brasileiro-de-Treinamento-Operacional#N`.

## Fluxo (obrigatório)

Issue ➡️ Branch (`feature/…`, `fix/…`, `docs/…` a partir da `main` atualizada) ➡️ Commits em pt-BR (`tipo(assunto): o que e por quê`) ➡️ `composer ci:check` ➡️ `git push -u origin <branch>` ➡️ resultados na issue ➡️ teste humano (Aline ou Gleyson) ➡️ PR pronta ➡️ revisão ➡️ merge. Nunca commit direto na `main`, nada de `--force`. Merge e criação de issues/PRs só com o OK do Gleyson.

## Limites que valem para qualquer proposta

- **Servidor:** HostGator compartilhada, PHP 8.3, Percona 5.7.44, **sem SSH e sem Node**. `vendor/` e `public/build` são gerados fora do servidor e enviados prontos; comandos no servidor só por Cron Job.
- **PHP 8.3:** o `composer.json` tem `config.platform.php = 8.3.0`. Não use recursos do PHP 8.4, nem pacotes que o exijam (por isso o Pest é o 4).
- **MySQL 5.7:** migrations precisam rodar no Percona 5.7 (a CI confere). Nada de recursos só do MySQL 8 (CTE recursiva, funções de janela, `CHECK`, índices funcionais).
- Não propor Vercel, Supabase, Node ou Python para a infraestrutura.

## Padrão de qualidade

- **Segurança:** `.env` nunca no Git; validar entrada no servidor (Form Requests); Eloquent/consultas com parâmetros; erros públicos sem detalhes internos, com código estável (ex.: `SAUDE_BANCO_INDISPONIVEL`).
- **LGPD:** CPF, nascimento, endereço, telefone e documentos de filiados só no banco, criptografados, nunca em logs, testes com dados reais, issues ou commits.
- **Três idiomas (Gleyson, 29/09/2026):** toda tela tem a opção de **português (padrão), inglês e espanhol**, escolhida pelo usuário. Nenhum texto fixo no código: tudo passa pela camada de tradução, e uma tela só está pronta com as três versões.
- **Acessibilidade:** um `<h1>` por página, navegação por teclado, contraste WCAG AA, `<html lang>` no idioma escolhido.
- **Indexação:** `APP_INDEXAVEL=false` (noindex) até o lançamento.
- Lógica com regra de negócio (datas, status, valores, percentuais) ganha teste.
