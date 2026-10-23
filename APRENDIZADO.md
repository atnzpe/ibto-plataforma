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

## 07/10/2026 — #37 cadastros de apoio (área, nível, equipe)

- Regras do Laravel numa LISTA: cada item é uma regra só (`['nullable', 'max:2048']`); juntar com `|` dentro da lista quebra (erro 500 "validateNullable|... does not exist").
- O mesmo `max` muda de sentido: no texto conta letras (`max:100`), no arquivo conta KB (`max:2048` = 2 MB).
- Regra de negócio fica no servidor e ganha teste: grau máximo por categoria (`match`) e "para ativar a equipe precisa de cidade, UF, responsável, telefone e uma área". A tela só ajuda; quem garante é o servidor.

## 09/10/2026 — #58 nenhum erro em silêncio

- Tela React que quebra fica BRANCA, a menos que exista uma "rede de proteção" (error boundary): um componente de classe que troca a tela por um aviso e avisa o servidor.
- Todo aviso automático precisa de freio dos dois lados: o navegador manda no máximo 5 por página e o servidor aceita 10 por minuto, para um erro em "loop" não lotar o log.
- Ao apagar uma linha de dica, use Ctrl+Shift+K (apaga a linha inteira): uma barra `/` que sobra quebra o arquivo todo.

## 09/10/2026 — #36 login reforçado e Aluno automático

- A trava de login conta TODA tentativa, até a com senha certa: o limite por computador tem que caber uma turma inteira no mesmo Wi-Fi (por isso 50, e não 10).
- Uma consulta sem `return` é uma pergunta cuja resposta vai para o lixo: o `return` é quem entrega o resultado para quem chamou a função.
- Papel se busca pelo SLUG (`'aluno'`), o apelido fixo do sistema; o nome da tela ("Aluno") pode mudar.

## 10/10/2026 — #59 financeiro e Comando Geral

- Dinheiro em CENTAVOS (inteiro): `intdiv` dá a parte inteira e `%` o resto; nas parcelas, a sobra vai para a 1ª e a soma sempre bate.
- `&&` ("e") exige as duas condições (categoria em uso E troca de tipo); `||` ("ou") basta uma (valor zero OU acima do total). O ternário `? :` é um "se… então… senão" numa linha, e protege contra divisão por zero.
- O que foi pago nunca muda: corrige-se com estorno; o histórico é só leitura e um teste garante que não existe rota para apagá-lo. Número fixo espalhado no código é "número mágico": dê um nome (constante) e leia de um lugar só.

## 10/10/2026 — #37 "importar mesmo assim" e telas sem rolagem lateral

- Dado FALTANDO é diferente de dado ERRADO: o que falta pode entrar e ser completado depois (selo "Incompleto"); o errado (CPF inválido, repetido) continua barrado, como no PDV, que vende sem o CPF na nota, mas não com um CPF inválido.
- Para o banco aceitar "vazio", a coluna precisa ser `nullable` (aceita NULL); no MySQL, vários NULL numa coluna `unique` não contam como repetidos.
- Uma regra de estilo única (`tabela-cartoes`) vale para todas as tabelas: abaixo de 1024 px cada linha vira um cartão, e o `data-rotulo` leva o nome da coluna para dentro do cartão.

## 10/10/2026 — cursos: trilha, sala de aula, cronograma; contas e identidade visual

- Desativar não é apagar: uma data (`desativada_em`) e um "porteiro" (middleware) que confere cada pedido bastam para trancar a conta sem perder o histórico.
- O lembrete de agenda é um arquivo de texto (.ics) com regras próprias: horário em UTC, vírgula e ponto e vírgula com "\" na frente, linhas de até 75 bytes.
- Cor de marca também é acessibilidade: o contraste entre texto e fundo se CALCULA (mínimo 4,5:1), não se escolhe no olho.

## 14/10/2026 — #44 chamada, certificado e QR code

- O certificado guarda uma "foto" do dia da emissão (nome, curso, carga horária): se o curso mudar depois, o certificado já entregue não muda. A validação pública mostra só o nome abreviado (LGPD).
- QR code em SVG é um desenho feito de texto, gerado no nosso servidor: fica nítido impresso e o endereço do certificado não passa por site de terceiros.
- No PHP, o sinal que fecha o código (`?` seguido de `>`) encerra o PHP até dentro de um comentário `//`: o arquivo inteiro quebra com "Unclosed '{'".

## 14/10/2026 — curso fechado, matrícula pelo IBTO e divisão do dinheiro

- 404 em vez de 403 esconde que a página existe: quem não pode ver um curso fechado nem descobre que ele existe.
- O percentual mora em cada curso (e não num "se o nome for Formação…"): uma exceção nova vira só um número no cadastro, sem mexer no código.
- Divisão em centavos: a parte do IBTO arredonda para baixo (`intdiv`) e o instrutor fica com o resto; assim a soma das partes sempre bate com o valor pago.

## 14/10/2026 — progresso das aulas e "Meu dossiê"

- Regra usada por duas telas mora num SERVIÇO (`SalaDeAula`): a sala de aula e "Meus cursos" fazem a mesma conta, sem copiar código.
- `flatMap` devolve uma lista comum, sem os atalhos de lista de modelos (`modelKeys`); `pluck('id')` funciona nas duas.
- Página "só minha" não leva número de pessoa no endereço: o servidor usa quem está logado, então não dá para trocar o número e ver o dossiê de outro.

## 16/10/2026 — blog no site e tour guiado

- A vitrine pública mora no site e o cadastro na plataforma: a API entrega os dados e o site desenha; um redirecionamento com "plano B" (sem site configurado, a página continua abrindo) evita que algo some se o site cair.
- Preferência da pessoa (tour ligado ou desligado, telas vistas) fica na CONTA, não no navegador: assim vale no computador e no celular. O valor padrão vai no banco E no modelo (`$attributes`), senão um usuário recém-criado chega "desligado" na primeira tela.
- Balão de tour que aponta para um elemento que pode não existir precisa ser pulado (filtro antes de abrir), senão o tour quebra quando a tela muda.

## 16/10/2026 — e-mails com a marca, deploy sem erro 500 e fotos (#78)

- Erro 500 logo depois do deploy era o código novo chegando antes do banco e do cache de rotas: a "placa em atualização" (o mesmo arquivo do `php artisan down`) troca a tela quebrada por um aviso calmo até o Cron terminar. Sem SSH, um job do GitHub que lê o log por FTPS (escondendo e-mails) achou a causa em minutos.
- Foto de pessoa é dado pessoal: fica na pasta PRIVADA e só sai por uma rota que confere a permissão (portão `usuario.foto`), como a ficha do aluno que só o professor e a secretaria abrem. Quem não pode ver recebe 404, que não confirma nem que a foto existe.
- Grave a foto nova ANTES de apagar a antiga: se algo falhar no meio, a pessoa nunca fica sem nenhuma foto. Consentimento (LGPD) começa desmarcado e guarda data e quem marcou.

**Perguntas para fixar:**

1. Por que a rota da foto devolve 404 (e não 403) para quem não pode ver?
2. O que a "placa em atualização" resolve que a ordem de envio dos arquivos não resolvia?
3. Por que a foto é recortada em quadrado e reduzida para 400 px antes de gravar?

## 23/10/2026 — níveis livres (nome, posição e letra)

- Para guardar uma ORDEM que muda, a coluna "posicao" com saltos de 10 (10, 20, 30...) deixa espaço para encaixar um item no meio (21) sem mexer nos outros; depois, uma "renumeração" volta tudo para 10, 20, 30. É como as faixas de uma academia: dá para criar uma faixa nova entre duas sem reimprimir a tabela inteira.
- Gravar o nível e renumerar a ordem vão juntos numa transação (`DB::transaction`): ou as duas coisas acontecem, ou nenhuma, e a hierarquia nunca fica pela metade.
- A conferência de "nome repetido" compara o nome FINAL que aparece na tela (escolhido ou automático), sem diferença de maiúsculas: "grão-mestre" e "Grão-Mestre" são o mesmo nível para quem lê.

**Perguntas para fixar:**

1. Por que a posição começa em 10 e não em 1?
2. O que aconteceria se o servidor caísse entre gravar o nível novo e renumerar os outros, sem a transação?
3. Por que o campo "Posição na hierarquia" não oferece o próprio nível como opção na edição?
