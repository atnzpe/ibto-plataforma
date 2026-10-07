# CLAUDE.md — IBTO Plataforma de cursos

Como trabalhar neste repositório. O README explica o projeto. As regras gerais do IBTO (pessoas, idioma, fluxo, LGPD, economia de tokens) estão no [CLAUDE.md do site](https://github.com/atnzpe/Instituto-Brasileiro-de-Treinamento-Operacional/blob/main/CLAUDE.md) e valem aqui também; abaixo, só o que é próprio da plataforma.

> **Aprendizado:** este projeto também é um ambiente de aprendizado do autor. Explique o porquê das decisões em linguagem simples, divida o trabalho em passos pequenos e proponha trechos curtos para ele escrever e você revisar.

## Regra inegociável: código comentado para qualquer pessoa entender

Todo código escrito ou alterado neste projeto é comentado de forma que uma pessoa leiga entenda o que cada parte faz ou deixa de fazer:

- **Arquivo:** começa com um comentário dizendo para que ele serve.
- **Função, método e componente:** comentário antes, explicando o que faz, cada argumento (o que é, com um exemplo) e o que devolve.
- **Variável e constante:** comentário dizendo o que guarda e por quê.
- **Cada linha ou pequeno bloco de lógica:** comentário dizendo o que faz e por quê; numa condição, também o que acontece quando ela não é verdadeira.
- **Idioma e tom:** português do Brasil, linguagem simples; todo termo técnico vem explicado.
- **Vale para tudo:** TypeScript/TSX, PHP, SQL (migrations, policies e funções), Python, CSS, HTML e workflows YAML.
- **Comentário acompanha o código:** ao alterar uma linha, atualize o comentário dela. Comentário desatualizado conta como bug.
- **Código antigo sem comentários:** comente o trecho que for alterado; não é preciso reescrever o projeto inteiro de uma vez.

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
- **Três idiomas pelo Google Tradutor (Gleyson, 07/10/2026; substitui a regra de 29/09):** toda tela tem o menu de idiomas (`resources/js/components/menu-idiomas.tsx`) com **português (padrão), inglês e espanhol**, sem tradução própria. As telas públicas usam `<MenuIdiomas />`, que abre a página traduzida pelo Google (translate.goog, mesmo método da CBSA). As telas com login e as de entrar/cadastrar usam `<MenuIdiomas modo="navegador" />`, com a dica do tradutor do navegador, porque no endereço do Google a pessoa perde o login. O script antigo do Google (`translate_a/element.js`) foi testado e não traduz as telas em React: não usar.
- **Acessibilidade:** um `<h1>` por página, navegação por teclado, contraste WCAG AA, `<html lang>` no idioma escolhido.
- **Indexação:** `APP_INDEXAVEL=false` (noindex) até o lançamento.
- Lógica com regra de negócio (datas, status, valores, percentuais) ganha teste.
