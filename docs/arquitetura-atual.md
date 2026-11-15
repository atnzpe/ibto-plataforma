# Arquitetura atual da plataforma do IBTO (leitura de 15/11/2026)

Leitura do repositório inteiro e das issues abertas do épico #33, feita antes de preparar a produção (#56).
Serve para quem chega ao projeto, para estudo e como mapa de "o que falta antes da produção".

> Documento de referência: a pasta `docs/` vai junto no deploy, mas fica fora da parte pública do servidor (o endereço aponta só para `public/`).

## 1. Tamanho e forma

| Parte | Tamanho | Observação |
|---|---|---|
| PHP (`app/`) | 241 arquivos, ~28.600 linhas | Laravel 13, PHP 8.3 no servidor |
| React + TypeScript (`resources/js/`) | 432 arquivos, ~87.000 linhas | Inertia (o Laravel entrega as telas em React sem API separada) |
| Banco | 68 migrations, 59 tabelas | precisam rodar no Percona/MySQL 5.7 (a CI confere) |
| Rotas | ~214 rotas web + API pública para o site | API pública só de leitura, com cache de 5 min |
| Testes | 129 arquivos de teste (Pest) + Lighthouse | rodam a cada mudança no GitHub Actions |
| Versão | 0.2.0 | começa com 0 = ainda em homologação |

Segurança: varredura do histórico com gitleaks em 07/11/2026 (#85): nenhuma senha, chave ou token em nenhum commit.

## 2. Ambientes

| Ambiente | Endereço | Situação |
|---|---|---|
| Computador do desenvolvedor | `localhost:8000` | `APP_ENV=local` |
| Homologação (testes) | `cursos-homolog.ib-to.org` | no ar; publica sozinha a cada merge na `main` ([guia](./publicacao-homologacao.md)) |
| Produção (público) | `cursos.ib-to.org` | **não existe ainda** (#56); guia em [publicacao-producao.md](./publicacao-producao.md) |

Atenção: a homologação usa `APP_ENV=production` (para se comportar igual à produção). Por isso, para separar homologação e produção no código, use o **endereço** (`APP_URL`), não o `APP_ENV`.

## 3. Módulos prontos (no ar na homologação)

| Módulo | Issue |
|---|---|
| Base, publicação sem SSH, saúde, erros registrados | #35, #58 |
| Login com duas travas de tentativas, cadastro com aprovação (grupo fechado), 5 papéis, permissão por tela, auditoria | #36, #84 |
| Verificação em duas etapas (opcional na fase de testes) | #110 |
| Cadastros: áreas, níveis livres, equipes, filiados com dados criptografados, importação da planilha, país | #37, #11 |
| Financeiro: livro-caixa, parcelas, estorno, filtros, CSV e PDF; anuidade | #59, #37 |
| Cursos online e presenciais, encontros com chamada, provas, certificados com validação pública | #38–#46, #72, #88 |
| Blog e galeria de eventos (com regra de LGPD para publicar foto) e venda de fotos (desligada) | #61, #90 |
| Rede Nacional (fotos inteiras por estado) | #11, #105 |
| Biblioteca de técnicas para monitores e instrutores | #107 |
| Três idiomas pelo Google Tradutor | #53 |

## 4. Como a plataforma trata um EVENTO (ex.: Congresso Nacional IBTO, 21/02/2027)

Não existe o tipo "evento". Um evento entra como **curso presencial**:

| No cartaz | Na plataforma |
|---|---|
| Nome e frase de chamada | `cursos.titulo` e `cursos.chamada` |
| Data, horário e local | `data_inicio`, `data_fim`, `hora_inicio`, `hora_fim`, `local` |
| Temas (APH, drones, tiro...) | um **encontro** por tema (`curso_aulas` com dia, hora e "quem aplica") |
| Valor e vagas | `valor_centavos`, `vagas`; desconto por cupom |
| Público ou só filiados | `acesso` (aberto ou restrito) |
| Certificado | emitido pelo IBTO com presença mínima nos encontros |
| Fotos depois do evento | álbum ligado ao curso (`galeria_albuns.curso_id`) |

Na homologação, o Congresso foi cadastrado com dados de exemplo pelo comando `plataforma:carregar-congresso` (título começa com `[TESTE] `).

**O que um evento grande ainda não tem** (cada item vira issue só se a direção confirmar que precisa):

| Lacuna | Contorno de hoje | Tamanho |
|---|---|---|
| Mais de um instrutor por curso | escrever o nome em "quem aplica" de cada encontro | pequeno (ligado à #106) |
| Lotes e preços por tipo de público (filiado × público) | um preço só + cupom para filiado | médio |
| Trilhas em paralelo (escolher uma oficina no mesmo horário) | todos os temas em sequência | médio |
| Termo de responsabilidade para tiro com munição real | nenhum | **depende da direção e do jurídico** |
| Rótulo "Evento" na vitrine (hoje aparece "Curso") | nenhum | pequeno |

## 5. O que falta antes da produção (achados da leitura)

| Achado | Onde | Gravidade | Próximo passo |
|---|---|---|---|
| A migration de 17/10 carregava os posts e álbuns `[TESTE]` em **qualquer** banco novo, inclusive o da produção | `2026_10_17_000011_carregar_conteudo_piloto.php` | média | **corrigido em 15/11/2026**: trava pelo endereço, igual à do Congresso |
| **Não existe limpeza do conteúdo de teste.** Vários comandos de carga dizem "sai na limpeza antes da produção", mas a limpeza não foi escrita. Como a produção nasce de um banco novo, o risco é baixo; ele existe se alguém copiar o banco da homologação | `app/Console/Commands/Carregar*.php` | média | regra no guia de produção: **banco novo, nunca cópia**; comando de limpeza só se a direção quiser levar dados da homologação |
| **Não existe workflow de deploy da produção** (só o da homologação) | `.github/workflows/` | média | copiar o `deploy-homologacao.yml` com o ambiente `producao`, só pelo botão "Run workflow" |
| "Apagar conta" ainda apaga de vez (o certificado e o histórico ficam sem dono) | `Settings\ProfileController::destroy` | média (LGPD) | trocar por "pedir exclusão" (#47), depois da decisão da direção |
| Verificação em duas etapas da equipe está opcional | `SEGURANCA_EXIGIR_2FA_EQUIPE=false` | média | melhorar a tela (#110) e voltar a obrigar antes de abrir ao público |
| Encarregado de dados (DPO) sem nome na política de privacidade | `/privacidade` | média (LGPD) | decisão da direção (#47) |
| Licença ainda "todos os direitos reservados"; faltam SECURITY.md, CONTRIBUTING.md e CODE_OF_CONDUCT.md | raiz do repositório | baixa agora | só depois da autorização por escrito do Mestre Eduardo (#85) |
| Duas branches antigas fora da `main` (`feature/11-pais`, substituída pela `11-pais-atualizada`; `fix/email-fila-conexao`, de 17/10) | GitHub | baixa | conferir se a correção do e-mail já entrou por outro caminho e apagar as duas |
| Lista de cursos leva 4–5 s num celular lento | hospedagem compartilhada | aceito em 24/10 | rever se o piloto mostrar lentidão |

## 6. Decisões abertas (dependem da direção do IBTO)

1. **Data e grupo do piloto.**
2. **Cadastro nos cursos públicos** (#108): o aluno se cadastra sozinho ou pede acesso?
3. **Encarregado de dados (DPO)** e **pedido de exclusão** no lugar de "apagar conta" (#47).
4. **Produção** (#56): quem cria o subdomínio, o banco e a conta FTP no cPanel do IBTO.
5. **Congresso Nacional:** valor, lotes, vagas, programação, instrutores e o termo do tiro com munição real.
6. **Código aberto** (#85): autorização por escrito do Mestre Eduardo e a futura organização "IBTO" no GitHub.
