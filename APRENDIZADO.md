# Aprendizado — IBTO Plataforma

Diário curto do que o Gleyson aprendeu em cada tarefa (3 linhas por tarefa).

## 07/10/2026 — #35 Parte 2: e-mail da homologação

- Log vazio também é pista: com `LOG_LEVEL=error`, só erros são gravados; sem arquivo do dia, o envio "não falhou", então a causa estava na configuração (`MAIL_MAILER=log` só anota, não envia).
- O Titan só aceita envio por outro programa (a plataforma) se "outros aplicativos" estiver ativado, e o remetente (`MAIL_FROM_ADDRESS`) precisa ser a mesma conta do login.
- `bootstrap/cache/config.php` é uma "foto" do `.env`: enquanto existir, a plataforma ignora o `.env` novo; apagou, vale na hora.

## 07/10/2026 — #35 Parte 3: vendor em .zip

- Padrão "ler → limpar (`trim`) → comparar → sair cedo": serve para saber se algo mudou (versão, nota fiscal, estoque); escrevi o trecho de `deploy/atualizar-vendor.php` e os 7 testes passaram de primeira.
- TDD: rodar o teste antes do código e ver falhar prova que o teste confere de verdade; depois o código faz passar.
- O PHPStan avisa casos que o teste não pega: `file_get_contents` pode devolver `false`, e o `trim` só aceita texto.

## 07/10/2026 — #35 Parte 3: primeiro deploy do vendor.zip

- "Deu sucesso" no GitHub não prova que chegou no lugar certo: quem confirma é o servidor (`VERSAO_DEPLOY`, `vendor-aplicado`, `versao-aplicada`).
- O cPanel preenche sozinho o diretório da conta FTP; a nossa ficou em `/public` e o código foi parar na parte pública. Conferir o caminho antes de salvar.
- Erro 500 é problema do servidor de quem responde (aqui, o GitHub): esperar e tentar de novo, sem mudar nada do nosso lado.

## 07/10/2026 — #35 Parte 4: e-mail de confirmação pela fila

- Herança (`extends VerifyEmail`): a classe nova ganha o e-mail pronto do Laravel sem reescrever nada; só muda o jeito de entregar.
- Contrato (`implements ShouldQueue`) + ferramenta (`use Queueable`): é o "carimbo" que manda o e-mail para a tabela `jobs` em vez de sair na hora; se o Titan falhar, o cadastro não dá 500 e o Cron tenta 3 vezes.
- `Queue::fake()` troca a fila por uma que só anota: o teste confere que o pedido entrou na fila sem mandar e-mail de verdade.

## 07/10/2026 — #53 idiomas pelo Google Tradutor

- A mesma regra em outra linguagem: a `hostGoogle` da CBSA (PHP, `str_replace`) virou TypeScript (`replaceAll`), e a ordem continua importando (primeiro o hífen, depois o ponto).
- Toda função de "ida" pode precisar da "volta": na página do Google, o menu descobre o nosso domínio desfazendo o apelido (`hostOriginal`).
- Teste no navegador antes de construir em cima: o script antigo do Google mostrou "Traduzido para", mas não traduziu o React; o login some no endereço do Google; por isso as telas com login usam a dica do tradutor do navegador.
