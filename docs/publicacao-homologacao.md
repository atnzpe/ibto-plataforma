# Publicação da homologação (cursos-homolog.ib-to.org)

Guia da issue #35 (parte 2). A homologação é onde a Aline e o Gleyson testam a plataforma antes da produção. Fica protegida por senha e fora do Google (`noindex`).

## Como funciona

```text
merge na main ➡️ GitHub Actions: CI completa ➡️ monta o pacote (vendor.zip sem dev + front compilado)
➡️ envia por FTPS para ~/cursos-homolog.ib-to.org ➡️ Cron Job (a cada 5 min)
➡️ deploy/atualizar-vendor.php descompacta o vendor.zip se as bibliotecas mudaram
➡️ plataforma:pos-deploy vê a versão nova ➡️ migrations + caches ➡️ agendador (fila de e-mails)
```

- O plano P da HostGator **não tem SSH nem Node**. Tudo o que precisa de `composer` ou `bun` roda no GitHub Actions; o servidor só recebe arquivos prontos.
- O projeto inteiro fica em `~/cursos-homolog.ib-to.org/`, mas o subdomínio aponta **só** para a subpasta `public/`. Assim o `.env`, o `vendor` e o código nunca ficam acessíveis pela internet.
- Comandos no servidor rodam pelo **Cron Job**, que chama três comandos em sequência: `php deploy/atualizar-vendor.php` (bibliotecas do PHP), `php artisan plataforma:pos-deploy` (migrations e caches) e `php artisan schedule:run` (agendador: fila de e-mails; as tarefas estão em `routes/console.php`).
- O `vendor` (bibliotecas do PHP, ~8.300 arquivos) vai num único `vendor.zip`, porque arquivo por arquivo a HostGator derrubava o FTP. O deploy grava em `VENDOR_VERSAO` uma "impressão digital" do `composer.lock` + `composer.json`; o `deploy/atualizar-vendor.php` compara com a última aplicada (`storage/app/vendor-aplicado`) e, se mudou, descompacta numa pasta nova e só troca pela atual se deu tudo certo. Ele é PHP puro, sem Laravel, porque o `artisan` não liga sem um `vendor` bom. O FTP nunca envia nem apaga a pasta `vendor` do servidor.
- Por que o pós-deploy vem **antes** e fora do agendador: o agendador usa a tabela `cache` do banco, que só existe depois das migrations. Se as migrations dependessem do agendador, o primeiro deploy nunca andaria ("ovo e galinha").
- O deploy grava o commit publicado em `VERSAO_DEPLOY`. O `plataforma:pos-deploy` compara com a última versão aplicada (`storage/app/versao-aplicada`) e, se for nova, roda `migrate --force`, `optimize:clear` e `optimize`. Se falhar, registra `POS_DEPLOY_FALHOU` no log do dia (`storage/logs/laravel-AAAA-MM-DD.log`) e tenta de novo na próxima rodada.
- O `optimize` guarda a configuração do `.env` em cache (`bootstrap/cache/config.php`). Por isso, **depois de editar o `.env` no servidor, apague `bootstrap/cache/config.php`**; senão a plataforma continua usando os valores antigos.

## Configuração inicial (uma vez só, no cPanel e no GitHub)

Faça na ordem. Cada parte termina num **ponto seguro**: dá para parar ali e continuar depois.

### Parte A — Subdomínio

- [x] cPanel → **Domínios** (ou Subdomínios) → criar `cursos-homolog.ib-to.org`.
- [x] **Desmarque** "Share document root" (compartilhar a raiz com ib-to.org). Essa escolha é permanente.
- [x] Em "Document Root", escreva **`cursos-homolog.ib-to.org/public`** (com o `/public` no final).
- [x] cPanel → **SSL/TLS Status** → confira que o subdomínio ganhou o certificado (AutoSSL). Pode levar alguns minutos.
- [x] cPanel → **MultiPHP Manager** → `cursos-homolog.ib-to.org` → **PHP 8.3**.

**Ponto seguro:** subdomínio criado, apontando para `public/`, com HTTPS e PHP 8.3.

### Parte B — Banco de dados

- [x] cPanel → **Bancos de dados MySQL** → criar um banco (ex.: `edua1187_cursos_homolog`).
- [x] Criar um usuário com senha forte (use o gerador do cPanel) e **adicioná-lo ao banco com TODOS OS PRIVILÉGIOS**.
- [x] Anote nome do banco, usuário e senha num lugar seguro (gerenciador de senhas), **nunca** no Git ou na issue.

**Ponto seguro:** banco criado.

### Parte C — Conta de e-mail (confirmação de cadastro)

O e-mail do domínio `ib-to.org` fica no **Titan** (o e-mail grátis do plano da HostGator, escolhido em 05/10/2026; a escolha não pode ser desfeita). O Titan recebe e envia os e-mails do domínio; a plataforma envia pelo servidor de envio (SMTP) dele, `smtp.titan.email`.

- [x] Painel da HostGator → **E-mails** → **Titan** → criar a conta `comando@ib-to.org` (pedido do Mestre em 07/10/2026; antes era `cursos@ib-to.org`), que envia os e-mails da plataforma, e guardar a senha no gerenciador de senhas.
- [x] No Titan, ativar o acesso por **outros aplicativos** (IMAP/POP/SMTP) dessa conta. Sem isso, o Titan recusa a senha quando a plataforma tenta enviar. Passo a passo: [artigo do Titan](https://support.titan.email/hc/en-us/articles/900000215446-Configure-Titan-on-other-apps-using-IMAP-POP).
- [x] No `.env` do servidor (Parte G), use `MAIL_HOST=smtp.titan.email`, porta `465` e o endereço e a senha dessa conta.

### Parte D — Conta de FTP só da plataforma

- [x] cPanel → **Contas FTP** → **Adicionar conta FTP**.
- [x] Diretório: **`cursos-homolog.ib-to.org`** (a pasta do projeto, **sem** o `/public`). **Cuidado:** ao digitar o login, o cPanel preenche este campo sozinho; apague e escreva o caminho à mão. Na lista de contas, o diretório deve ficar `/home1/edua1187/cursos-homolog.ib-to.org`, sem nada depois. (Em 07/10/2026 a primeira conta tinha ficado em `…/public`: o deploy pôs o código dentro da parte pública e foi preciso limpar o `public/` e criar uma conta nova. O cPanel não deixa trocar o diretório de uma conta existente.)
- [x] Anote o **usuário completo** que aparece em "Configurar cliente FTP".

### Parte E — Segredos no GitHub

No repositório `atnzpe/ibto-plataforma` → **Settings** → **Environments** → **New environment** → `homologacao`. Dentro dele, em **Environment secrets**:

| Segredo        | Valor                                                                                            |
| -------------- | ------------------------------------------------------------------------------------------------ |
| `FTP_SERVER`   | `br1098.hostgator.com.br` (o nome que o certificado da HostGator cobre; **não** `ftp.ib-to.org`) |
| `FTP_USERNAME` | o usuário completo da Parte D                                                                    |
| `FTP_PASSWORD` | a senha da conta FTP                                                                             |

**Ponto seguro:** o GitHub já consegue publicar.

### Parte F — Primeiro deploy

- [x] O GitHub só mostra um workflow na aba **Actions** depois que o arquivo dele chega à `main`. Por isso, o **primeiro deploy** é feito pelo merge da PR da `feature/publicacao-homolog`: o deploy roda sozinho a cada merge na `main`.
- [x] Depois disso, para publicar outra branch sem merge: GitHub → **Actions** → **Deploy homologação** → **Run workflow** → escolha a branch → **Run**.
- [x] O envio leva poucos minutos: o `vendor` vai num zip só (~20 MB, reenviado em todo deploy) e o resto manda só o que mudou.
- [x] O passo final "Conferir que está no ar" só confere o HTTPS e a senha (feitos pelo servidor, antes do Laravel); ele passa mesmo sem o `.env`. A plataforma em si só funciona depois das Partes G e H.

### Parte G — `.env` do servidor

- [x] Na sua máquina, na pasta da plataforma: `php artisan key:generate --show` → copie a chave (`base64:...`).
- [x] Abra [env-homologacao.exemplo](./env-homologacao.exemplo), preencha o que está entre `< >` (chave, banco, e-mail).
- [x] cPanel → **Gerenciador de Arquivos** → `cursos-homolog.ib-to.org` (ao lado do `artisan`, **não** dentro de `public`) → **+ Arquivo** → `.env` → cole o conteúdo preenchido → salvar.
- [x] Permissão do `.env`: **600** (clique direito → Change Permissions).
- [x] Apague do seu computador a cópia preenchida, se tiver salvo uma.

> **Editou o `.env` depois?** Apague também `bootstrap/cache/config.php` (Gerenciador de Arquivos). Esse arquivo é o cache da configuração: enquanto ele existir, a plataforma ignora o `.env` novo. Ele é recriado sozinho no próximo deploy.

### Parte H — Cron Job

- [x] cPanel → **Cron Jobs** → "Configuração comum": **a cada 5 minutos** (`*/5 * * * *`).
- [x] Comando (troque o caminho do PHP se o cPanel mostrar outro para o 8.3):

```bash
/opt/cpanel/ea-php83/root/usr/bin/php /home1/edua1187/cursos-homolog.ib-to.org/deploy/atualizar-vendor.php >> /dev/null 2>&1; /opt/cpanel/ea-php83/root/usr/bin/php /home1/edua1187/cursos-homolog.ib-to.org/artisan plataforma:pos-deploy >> /dev/null 2>&1; /opt/cpanel/ea-php83/root/usr/bin/php /home1/edua1187/cursos-homolog.ib-to.org/artisan schedule:run >> /dev/null 2>&1
```

- São três comandos separados por `;`: primeiro as bibliotecas (vendor), depois o pós-deploy (migrations), por fim o agendador. O `;` faz o seguinte rodar mesmo se o anterior falhar. A ordem importa: o `artisan` precisa do `vendor` pronto, e o agendador precisa da tabela `cache`, criada pelas migrations.
- [x] **Desde 07/10/2026 (Parte 3 da #35):** troque o comando do Cron pelo de três partes acima, **antes** do próximo deploy.
- [x] Espere 5 minutos. No Gerenciador de Arquivos deve aparecer `storage/app/versao-aplicada` (as migrations rodaram). Se não aparecer, abra o log do dia, `storage/logs/laravel-AAAA-MM-DD.log` (ex.: `laravel-2026-10-05.log`; os logs ficam 14 dias), e procure `POS_DEPLOY_FALHOU`; mande o código para o Claude.

**Ponto seguro:** plataforma no ar.

## Conferir (critério de aceite da #35)

1. `http://cursos-homolog.ib-to.org` redireciona para `https://` sem pedir senha.
2. `https://cursos-homolog.ib-to.org` pede a **senha da homologação** (a mesma de `homolog.ib-to.org`).
3. Depois da senha, a página inicial mostra ✅ "Plataforma no ar e banco de dados conectado" (vem de `/api/saude`).
4. Criar conta → o e-mail de confirmação chega (até 5 minutos, pela fila) → entrar → painel → sair.

## Problemas comuns

| Sintoma                                                    | Causa provável                                                                                                                                                                                      |
| ---------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| "Internal Server Error" logo depois da senha               | `AuthUserFile` do `public/.htaccess` não acha `/home1/edua1187/.htpasswds/homolog.ib-to.org/passwd`                                                                                                 |
| Erro 500 sem senha nenhuma                                 | Falta o `.env` ou o `APP_KEY`                                                                                                                                                                       |
| Página abre, situação mostra ❌ `SAUDE_BANCO_INDISPONIVEL` | Dados do banco errados no `.env`, ou o usuário não foi adicionado ao banco                                                                                                                          |
| Mudança nova não aparece                                   | O Cron ainda não rodou (até 5 min) ou `POS_DEPLOY_FALHOU` no log do dia (`storage/logs/laravel-AAAA-MM-DD.log`)                                                                                     |
| Editei o `.env` no servidor e nada mudou                   | A plataforma ainda lê a cópia antiga em `bootstrap/cache/config.php`: apague esse arquivo e a mudança vale imediatamente                                                                            |
| E-mail não chega e não existe log do dia                   | `MAIL_MAILER=log` no `.env` (só anota, não envia): trocar para `smtp` e apagar `bootstrap/cache/config.php`                                                                                         |
| E-mail não sai e o log mostra erro de autenticação         | Acesso por "outros aplicativos" desligado no Titan (Parte C) ou senha errada no `.env`                                                                                                              |
| `storage/logs/vendor-AAAA-MM-DD.log` com `VENDOR_FALHOU`   | O `vendor.zip` não descompactou (zip incompleto ou falta de espaço). O vendor antigo continua funcionando e o Cron tenta de novo em 5 min; se repetir, rodar o deploy de novo                       |
| `storage/logs/vendor-AAAA-MM-DD.log` com `VENDOR_SEM_ZIP`  | O PHP 8.3 do servidor está sem a extensão zip: cPanel → **Select PHP Version** → marcar `zip` (ou pedir ao suporte da HostGator)                                                                    |
| E-mail de confirmação não chega                            | Fila parada (Cron) ou senha do e-mail errada no `.env`                                                                                                                                              |
| E-mail sumiu depois de 3 tentativas                        | Falhou 3 vezes e foi para a tabela `failed_jobs` (cPanel → phpMyAdmin → coluna `exception` mostra o motivo). Corrigir a causa (senha, Titan) e pedir o reenvio pelo botão da tela                   |
| Pessoa diz que "não deixa entrar" ou "não tem permissão"   | Ver `storage/logs/seguranca-AAAA-MM-DD.log`: `LOGIN_BLOQUEADO` (muitas tentativas; espere 1 minuto) ou `PERMISSAO_NEGADA` (falta o papel ou a permissão da tela; ajustar em Usuários ou Permissões) |
| Tela mostra "Algo deu errado" com `ERRO_TELA`              | Erro na tela React: procurar `ERRO_TELA` no log do dia (`storage/logs/laravel-AAAA-MM-DD.log`), que traz a mensagem e a página                                                                      |
| Lista de arquivos ou o código aparece no navegador         | Document Root sem o `/public` (Parte A) — corrigir antes de qualquer outra coisa                                                                                                                    |

O deploy nunca envia nem apaga o `.env`, os logs ou o `storage/app/versao-aplicada`. O arquivo `.ftp-deploy-sync-state.json` (lista do que já foi enviado) fica na pasta do projeto, fora da parte pública.
