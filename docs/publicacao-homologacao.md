# Publicação da homologação (cursos-homolog.ib-to.org)

Guia da issue #35 (parte 2). A homologação é onde a Aline e o Gleyson testam a plataforma antes da produção. Fica protegida por senha e fora do Google (`noindex`).

## Como funciona

```text
merge na main ➡️ GitHub Actions: CI completa ➡️ monta o pacote (vendor sem dev + front compilado)
➡️ envia por FTPS para ~/cursos-homolog.ib-to.org ➡️ Cron Job (a cada 5 min) roda o agendador
➡️ plataforma:pos-deploy vê a versão nova ➡️ migrations + caches
```

- O plano P da HostGator **não tem SSH nem Node**. Tudo o que precisa de `composer` ou `bun` roda no GitHub Actions; o servidor só recebe arquivos prontos.
- O projeto inteiro fica em `~/cursos-homolog.ib-to.org/`, mas o subdomínio aponta **só** para a subpasta `public/`. Assim o `.env`, o `vendor` e o código nunca ficam acessíveis pela internet.
- Comandos no servidor (migrations, caches, fila de e-mails) rodam pelo **Cron Job**, que chama `php artisan schedule:run`. As tarefas estão em `routes/console.php`.
- O deploy grava o commit publicado em `VERSAO_DEPLOY`. O `plataforma:pos-deploy` compara com a última versão aplicada (`storage/app/versao-aplicada`) e, se for nova, roda `migrate --force`, `optimize:clear` e `optimize`. Se falhar, registra `POS_DEPLOY_FALHOU` no log e tenta de novo na próxima rodada.

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

- [x] Painel da HostGator → **E-mails** → **Titan** → criar a conta `cursos@ib-to.org`, que envia os e-mails da plataforma, e guardar a senha no gerenciador de senhas.
- [ ] No `.env` do servidor (Parte G), use `MAIL_HOST=smtp.titan.email`, porta `465` e o endereço e a senha dessa conta.

### Parte D — Conta de FTP só da plataforma

- [x] cPanel → **Contas FTP** → **Adicionar conta FTP**.
- [x] Diretório: **`cursos-homolog.ib-to.org`** (a pasta do projeto, **sem** o `/public`).
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

- [ ] O GitHub só mostra um workflow na aba **Actions** depois que o arquivo dele chega à `main`. Por isso, o **primeiro deploy** é feito pelo merge da PR da `feature/publicacao-homolog`: o deploy roda sozinho a cada merge na `main`.
- [ ] Depois disso, para publicar outra branch sem merge: GitHub → **Actions** → **Deploy homologação** → **Run workflow** → escolha a branch → **Run**.
- [ ] O primeiro envio é demorado (o `vendor` tem milhares de arquivos). Os seguintes mandam só o que mudou.
- [ ] O passo final "Conferir que está no ar" só confere o HTTPS e a senha (feitos pelo servidor, antes do Laravel); ele passa mesmo sem o `.env`. A plataforma em si só funciona depois das Partes G e H.

### Parte G — `.env` do servidor

- [ ] Na sua máquina, na pasta da plataforma: `php artisan key:generate --show` → copie a chave (`base64:...`).
- [ ] Abra [env-homologacao.exemplo](./env-homologacao.exemplo), preencha o que está entre `< >` (chave, banco, e-mail).
- [ ] cPanel → **Gerenciador de Arquivos** → `cursos-homolog.ib-to.org` (ao lado do `artisan`, **não** dentro de `public`) → **+ Arquivo** → `.env` → cole o conteúdo preenchido → salvar.
- [ ] Permissão do `.env`: **600** (clique direito → Change Permissions).
- [ ] Apague do seu computador a cópia preenchida, se tiver salvo uma.

### Parte H — Cron Job

- [ ] cPanel → **Cron Jobs** → "Configuração comum": **a cada 5 minutos** (`*/5 * * * *`).
- [ ] Comando (troque o caminho do PHP se o cPanel mostrar outro para o 8.3):

```bash
/opt/cpanel/ea-php83/root/usr/bin/php /home1/edua1187/cursos-homolog.ib-to.org/artisan schedule:run >> /dev/null 2>&1
```

- [ ] Espere 5 minutos. No Gerenciador de Arquivos deve aparecer `storage/app/versao-aplicada` (as migrations rodaram). Se não aparecer, abra `storage/logs/` e procure `POS_DEPLOY_FALHOU`; mande o código para o Claude.

**Ponto seguro:** plataforma no ar.

## Conferir (critério de aceite da #35)

1. `http://cursos-homolog.ib-to.org` redireciona para `https://` sem pedir senha.
2. `https://cursos-homolog.ib-to.org` pede a **senha da homologação** (a mesma de `homolog.ib-to.org`).
3. Depois da senha, a página inicial mostra ✅ "Plataforma no ar e banco de dados conectado" (vem de `/api/saude`).
4. Criar conta → o e-mail de confirmação chega (até 5 minutos, pela fila) → entrar → painel → sair.

## Problemas comuns

| Sintoma                                                    | Causa provável                                                                                      |
| ---------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| "Internal Server Error" logo depois da senha               | `AuthUserFile` do `public/.htaccess` não acha `/home1/edua1187/.htpasswds/homolog.ib-to.org/passwd` |
| Erro 500 sem senha nenhuma                                 | Falta o `.env` ou o `APP_KEY`                                                                       |
| Página abre, situação mostra ❌ `SAUDE_BANCO_INDISPONIVEL` | Dados do banco errados no `.env`, ou o usuário não foi adicionado ao banco                          |
| Mudança nova não aparece                                   | O Cron ainda não rodou (até 5 min) ou `POS_DEPLOY_FALHOU` no log                                    |
| E-mail de confirmação não chega                            | Fila parada (Cron) ou senha do e-mail errada no `.env`                                              |
| Lista de arquivos ou o código aparece no navegador         | Document Root sem o `/public` (Parte A) — corrigir antes de qualquer outra coisa                    |

O deploy nunca envia nem apaga o `.env`, os logs ou o `storage/app/versao-aplicada`. O arquivo `.ftp-deploy-sync-state.json` (lista do que já foi enviado) fica na pasta do projeto, fora da parte pública.
