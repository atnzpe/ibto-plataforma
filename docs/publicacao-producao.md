# Publicação da produção (cursos.ib-to.org) — guia (#56)

> Documento de referência. Escrito em 15/11/2026, antes de a produção existir: **conferir cada tela no cPanel**.
> Toda etapa que muda algo no ar precisa do "sim" do responsável técnico, com a volta já combinada.
> A produção é um **clone** da homologação: siga o [guia da homologação](./publicacao-homologacao.md) trocando os nomes pela tabela da seção 3.

## 1. O que já está pronto (conferido em 15/11/2026)

| Item | Resultado |
|---|---|
| Homologação no ar desde 07/10/2026, publicando sozinha a cada merge | ok |
| Testes automáticos, tipos, estilo e Lighthouse a cada mudança | ok |
| Histórico sem senhas nem chaves (gitleaks, 07/11/2026) | ok |
| Pedidos já entregues: Rede Nacional (#11), fotos inteiras (#105), biblioteca de técnicas (#107) | no ar na homologação |
| Congresso Nacional IBTO com dados de exemplo | na homologação, marcado `[TESTE]` |

## 2. O que precisa vir do IBTO (sem isso, a produção não começa)

1. **Direção:** data e grupo do piloto.
2. **Direção:** cadastro dos cursos públicos (#108) e encarregado de dados, o DPO (#47).
3. **Quem cuida da HostGator do IBTO:** acesso ao cPanel (ou alguém junto) para criar o subdomínio `cursos.ib-to.org`, o banco e a conta FTP.
4. **Direção:** dados oficiais dos cursos e eventos que abrem primeiro (ex.: Congresso: valor, vagas, programação, instrutores).
5. **Equipe:** os e-mails de quem entra primeiro como administrador (vão no `ADMIN_EMAILS` só para a primeira entrada).

## 3. O que muda em relação à homologação

| Item | Homologação | Produção |
|---|---|---|
| Endereço | `cursos-homolog.ib-to.org` | `cursos.ib-to.org` |
| Pasta no servidor | `~/cursos-homolog.ib-to.org` | `~/cursos.ib-to.org` |
| Banco | o da homologação | **banco novo, vazio** (nunca cópia do de testes) |
| Ambiente no GitHub | `homologacao` | `producao` |
| Deploy | automático a cada merge na `main` | **só pelo botão "Run workflow"**, depois do teste humano |
| `APP_KEY` | a da homologação | **nova** (`php artisan key:generate --show`) |
| Cargas de teste (`PILOTO_*`) | ligadas | **todas vazias / false** |
| `SEGURANCA_EXIGIR_2FA_EQUIPE` | false (fase de testes) | true (depois da #110) |
| `APP_INDEXAVEL` | false | false até o lançamento; true no dia do lançamento |

## 4. Passo a passo

Ordem pensada para que nada mude para quem usa a homologação.

| # | O que fazer | Como conferir | Como voltar |
|---|---|---|---|
| 1 | Criar o workflow `deploy-producao.yml` (cópia do da homologação, ambiente `producao`, só "Run workflow") numa branch, com PR e revisão | CI verde na PR | Fechar a PR |
| 2 | cPanel: subdomínio `cursos.ib-to.org` com Document Root em `cursos.ib-to.org/public`, HTTPS e PHP 8.3 | Abre o aviso padrão do servidor com cadeado | Apagar o subdomínio |
| 3 | cPanel: banco e usuário **novos** | Usuário aparece ligado ao banco | Apagar o banco |
| 4 | cPanel: conta FTP com diretório `cursos.ib-to.org` (sem `/public`; ver o aviso na Parte D do guia da homologação) | Diretório da conta sem nada depois do nome da pasta | Apagar a conta FTP |
| 5 | GitHub: ambiente `producao` com `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` | Os 3 segredos aparecem no ambiente | Apagar o ambiente |
| 6 | Primeiro envio pelo botão "Run workflow" (ambiente `producao`) | Arquivos na pasta certa; `public/` só com o necessário | Apagar os arquivos enviados |
| 7 | `.env` de produção ao lado do `artisan`, permissão 600 (seção 3) | Página de login abre | Apagar o `.env` |
| 8 | Cron Job com os 3 comandos, trocando a pasta para `cursos.ib-to.org` | Aparece `storage/app/versao-aplicada` em até 5 min | Apagar o Cron |
| 9 | Primeira entrada com o e-mail do `ADMIN_EMAILS`; criar a conta de Tecnologia; esvaziar o `ADMIN_EMAILS` | Lista da seção 5 | Desativar a conta |
| 10 | Avisar a direção e acompanhar por uma semana | Sem queixas | A homologação continua igual |

## 5. Lista de conferência (rode na produção)

- [ ] Login, Meu painel, catálogo, um curso, certificado (validação pública) e `/privacidade`.
- [ ] Nenhum título com `[TESTE]` no catálogo, no blog nem nos eventos.
- [ ] `/.env`, `/storage/`, `/vendor/` e `/docs/` respondem 403 ou 404.
- [ ] `http://` vira `https://`.
- [ ] E-mail de convite chega (Titan), com `spf=pass` no cabeçalho.
- [ ] `/saude` responde ok.
- [ ] A página tem `noindex` até o lançamento.

## 6. Armadilhas já identificadas

1. **`APP_ENV=production` também na homologação.** Código que precisa separar os dois (ex.: a carga do Congresso, migration `2026_11_15_000001`) usa o `APP_URL`. Em produção, o `APP_URL` **não** pode ter a palavra "homolog".
2. **Não existe limpeza do conteúdo `[TESTE]`.** Por isso o banco da produção nasce vazio. Se a direção quiser levar algo da homologação (ex.: filiados já cadastrados), abrir uma issue para um comando de limpeza testado antes.
3. **Conta FTP com o diretório errado.** O cPanel preenche o diretório sozinho; já aconteceu na homologação (07/10/2026). Ele não deixa trocar depois: é preciso criar outra conta.
4. **Cache da configuração.** Depois de editar o `.env`, apagar `bootstrap/cache/config.php`.
5. **Migrations de carga de piloto rodam em todo banco novo.** As migrations `2026_10_17_000011` (conteúdo do piloto) e `2026_11_15_000001` (Congresso) chamam comandos de carga. Desde 15/11/2026, as duas só rodam se o `APP_URL` for da homologação ou do computador local. Migration nova que carregue dados de teste **precisa da mesma trava**.
