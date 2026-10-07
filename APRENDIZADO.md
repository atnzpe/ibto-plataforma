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
