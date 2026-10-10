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

## 10/10/2026 — cursos: trilha, sala de aula, cronograma; contas e identidade visual

- Desativar não é apagar: uma data (`desativada_em`) e um "porteiro" (middleware) que confere cada pedido bastam para trancar a conta sem perder o histórico.
- O lembrete de agenda é um arquivo de texto (.ics) com regras próprias: horário em UTC, vírgula e ponto e vírgula com "\" na frente, linhas de até 75 bytes.
- Cor de marca também é acessibilidade: o contraste entre texto e fundo se CALCULA (mínimo 4,5:1), não se escolhe no olho.
