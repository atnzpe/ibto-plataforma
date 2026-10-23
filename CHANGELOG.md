# Histórico de versões (CHANGELOG)

Todas as mudanças que importam para quem usa a plataforma ficam aqui, da mais nova para a mais antiga.
O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e as versões seguem o [Versionamento Semântico](https://semver.org/lang/pt-BR/):

- **MAIOR** (1.0.0): mudança que quebra algo para quem já usa (ex.: a produção no ar com uma regra nova de dados).
- **MENOR** (0.**2**.0): funcionalidade nova, sem quebrar nada.
- **CORREÇÃO** (0.1.**1**): conserto, sem funcionalidade nova.

Enquanto a versão começar com **0**, a plataforma está em homologação (antes do piloto e da produção).

## [Não lançado]

### Adicionado (níveis livres)

- O Comando Geral cria níveis novos com nome próprio (ex.: "Grão-Mestre" ou "Monitor III"), escolhe depois de qual nível cada um entra na hierarquia e a letra do número de série do certificado de graduação. A ordem vale em toda a plataforma (listas, pré-requisito de nível mínimo dos cursos, importação). Os 13 níveis de hoje continuam iguais, na mesma ordem.

### Adicionado (certificado de graduação, #46)

- Certificado de graduação no modelo institucional do Mestre Eduardo: emitido na ficha do filiado (textos sugeridos pelo nível e pelas áreas), número de série GR + ano + ordem + letra do nível + via, QR code com a validação pública (nome abreviado), cancelamento com motivo e a lista no Meu dossiê para imprimir.

### Adicionado (licença provisória)

- Arquivo LICENSE: repositório público só para leitura, todos os direitos reservados ao IBTO até a diretoria escolher a licença aberta (#85).

### Alterado (menu lateral em grupos)

- O menu lateral agora é organizado por assunto: Início, Minha área, Ensino, Pessoas, Financeiro, Comunicação e Cadastros. Cada grupo abre e fecha (a escolha fica guardada no navegador); o grupo da tela aberta já vem aberto; grupo de um item só aparece sem título.

### Segurança (preparação para o repositório público)

- Saiu do código o link de convite do grupo de WhatsApp de um curso, e uma migração tira o mesmo link da descrição já gravada no banco. Teste do catálogo usa número de WhatsApp falso.

### Alterado (economia de minutos do GitHub Actions)

- A CI roda uma vez por envio na PR (antes, duas); no deploy, pula os testes de navegador (já rodaram na PR); o Lighthouse roda só à mão ou toda segunda (antes, depois de cada deploy, esperando até 30 min).

### Alterado (LGPD, #47)

- Política de privacidade aprovada pela diretoria: versão 1.0 de 18/10/2026 (sai o aviso de rascunho). Com LGPD_EXIGIR_ACEITE=true no .env, todos aceitam a versão 1.0 no próximo acesso; o encarregado de dados vem de LGPD_ENCARREGADO.

### Desempenho (#74)

- Os textos dos tours e a biblioteca dos balões (driver.js) só são baixados quando o tour abre: o arquivo principal da tela caiu de 83,8 KB para 71,8 KB (comprimido). O botão "?" usa a lista curta de lib/tours-telas.ts.

### Corrigido (achados do Lighthouse)

- Privacidade: o campo da versão do texto cobria a versão da plataforma e quebrava o aviso do console (erro "reading 'versao'"); agora se chama versao_politica.
- Entrar e Privacidade ganharam a meta descrição (o resumo que o Google e o WhatsApp mostram).
- O relatório do Lighthouse passa a ser guardado nos artefatos do job (a pasta é oculta).

### Acessibilidade (#74)

- Auditoria automática de acessibilidade na CI: 15 telas (públicas, do aluno e do Comando Geral) abrem num navegador de verdade (Chromium) e passam pelo axe (regras WCAG); falha nova deixa a CI vermelha.
- Telas de entrar e de pedir acesso sem tabindex forçado: a ordem do teclado é a ordem natural da tela.
- Lighthouse depois de cada deploy na homologação (workflow "Lighthouse homologação"): catálogo, entrar e privacidade no celular, com acessibilidade, SEO e boas práticas ≥ 95 (bloqueante) e desempenho ≥ 90 (aviso); o relatório fica nos artefatos do job.

### Segurança (#74)

- Cabeçalhos de segurança em toda resposta: nosniff, anti-clickjacking (X-Frame-Options e frame-ancestors), Referrer-Policy, Permissions-Policy (câmera, microfone, localização e pagamento bloqueados), Cross-Origin-Opener-Policy, HSTS de 1 ano em HTTPS e política de conteúdo (CSP: scripts só da plataforma e da VLibras, vídeos só do YouTube, sem plugins, formulários só para a própria plataforma). `SEGURANCA_CSP=false` no .env desliga só a CSP, se algo externo deixar de carregar.

### Adicionado (provas dos cursos, #43)

- Provas de múltipla escolha por curso: o instrutor monta (título, nota mínima, tentativas e as perguntas com a alternativa certa) e vê quem fez, os aprovados, a média e a nota de cada aluno (só o registro).
- O aluno faz a prova na página do curso; a correção é no servidor (o gabarito nunca vai para a tela) e a nota aparece na hora.
- Com prova ativa, o certificado exige a aprovação em todas; aprovado com o curso concluído, o certificado automático sai na hora.

### Alterado (anuidade e cursos exclusivos)

- Curso exclusivo para filiados exige também a anuidade do ano em dia (#37): o filiado vencido não se inscreve (código MATRICULA_ANUIDADE_PENDENTE) e perde o acesso às aulas desses cursos até pagar. Ano isento (2026) conta como em dia.

### Alterado (níveis)

- Cadastros → Níveis: com os 13 níveis da hierarquia já cadastrados, o botão "Novo nível" some e a tela explica que não é preciso cadastrar nada (para tirar um nível de uso, desative).

### Adicionado (filtros dos relatórios)

- Relatórios com filtros robustos: por dimensão (equipe, modalidade, área, situação, instrutor) e por coluna de texto, busca livre, e modo analítico ou sintético (somado pelo campo escolhido). CSV e PDF exportam o mesmo filtro, e a auditoria guarda os filtros usados.

### Adicionado (vínculos do livro-caixa)

- Financeiro com filtros robustos: período pelo vencimento ou pelo pagamento; situação, tipo de movimento, categoria, forma, papel da pessoa, equipe, curso, programa, modalidade, pessoa (nome ou e-mail), código e faixa de valor. Cartões que respondem ao filtro (recebido, a receber, pago, a pagar e saldo em caixa); modo analítico ou sintético (por pessoa, categoria, movimento, curso, equipe, mês ou forma); CSV e PDF com o mesmo filtro, registrados na auditoria.

- Cada lançamento guarda a quem se refere: a pessoa (na receita, quem pagou; no repasse, o instrutor ou o fotógrafo que recebe), o curso e o programa de aulas regulares. Os lançamentos antigos foram preenchidos pela origem; estornos e saldos herdam os vínculos. É a base dos filtros por pessoa, papel, curso e equipe no Financeiro.

### Adicionado (diário de aulas)

- Aulas regulares: ao fazer a chamada, o instrutor escreve "O que foi ministrado nesta aula" (obrigatório).
- Cursos: "O que foi trabalhado no encontro" passa a ser obrigatório na chamada.
- Meu dossiê → Aulas regulares: total de presenças e % de frequência, e o "Diário de aulas" (as 30 mais recentes: data, presente/falta e o que foi visto).

### Adicionado (conferência do comprovante)

- Comprovante enviado: a página do curso e "Meus cursos" mostram "aguardando conferência · não precisa pagar de novo", com a data do envio.
- Comando Geral → Matrículas: "Recusar o comprovante" com motivo, sem cancelar a matrícula; o aluno recebe o motivo por e-mail e envia outro.

### Corrigido (entrar e sair)

- "Sair" funciona com um clique (antes o navegador bloqueava a ida ao site por CORS e era preciso clicar duas vezes).
- Depois do login, a plataforma só volta para a página guardada se a conta puder abri-la; senão, vai para o início (sem a tela 403).
- Quem tem o papel de filiado, mas ainda não tem a ficha ligada à conta, vê "Filiado do IBTO · ficha de filiado ainda não ligada à conta", e não mais "Aluno do IBTO".

### Adicionado (painel financeiro das aulas regulares)

- Meu painel: "Minha saúde financeira" (cursos + aulas: já recebi, pendente de repasse, a vencer e total a receber) e "Aulas regulares que ministro", por programa.

- Relatórios → "Aulas regulares (financeiro)" (17/10/2026), no molde do Cockpit do Dojo Manager: quem treina, previsto, recebido (com %), a receber, em atraso, a divisão IBTO/instrutor (já repassado e falta repassar), mês a mês, por programa e por instrutor. Filtros De/Até, programa, instrutor (recebedor), aluno (pagador) e situação; CSV auditado. Permissão nova `relatorio.aulas` (Administrador, Tecnologia e Instrutor; o instrutor vê só os programas dele).

### Corrigido (tela de manutenção)

- O emblema da tela de manutenção aparece recortado em círculo, sem o fundo quadrado escuro da imagem.

### Alterado (tela de manutenção)

- Tela de manutenção do IBTO (17/10/2026): durante o deploy, em vez da página genérica "503 Service Unavailable", aparece "Estamos atualizando a plataforma" com o emblema animado e 40 mensagens de treino (Sun Tzu, homem cinza, comunicação e mindfulness), em português, recarregando sozinha a cada 60 segundos; tudo embutido, para funcionar mesmo no meio da troca dos arquivos. Pedido do Gleyson: o emblema "respira" (parado para quem pede menos movimento) e uma de 30 mensagens de treino aparece, trocando a cada 15 segundos: A Arte da Guerra (Sun Tzu, tradução livre), mentalidade operacional e "homem cinza", treino e comunicação.

### Adicionado (manual e tour em nível militar)

- Tour guiado em TODAS as telas da plataforma (17/10/2026): cerca de 50 telas ganharam tour (aulas regulares, pagamento, fotos, carteirinha, privacidade, configurações, cadastros, financeiro, relatórios, galeria e blog); o "?" do tour passou a aparecer também na vitrine (catálogo, curso, trilha, blog, eventos, privacidade e validações) e na carteirinha; marcas `data-tour` nos botões principais (salvar chamada, Pix, 3 passos da compra, desfocar, fotos do curso).
- Manual de instruções completo: novas seções Áreas de atuação, Níveis, Galeria de eventos e Mensalidades.
- Testes rígidos: toda tela tem tour (só as de antes do login e as de impressão ficam de fora, com o motivo escrito), todo item do menu tem seção no manual, e toda seção do manual aponta para uma tela com tour; tela ou item de menu novo sem manual e sem tour reprova a CI.

### Adicionado (chamada das aulas regulares)

- Chamada das aulas regulares (#80, fecha a issue): "Minhas turmas" mostra ao instrutor as turmas dos programas dele (o Comando Geral vê todas); a chamada lista só os alunos em dia ou na carência, aceita corrigir um dia anterior sem duplicar e recusa data futura; a frequência mês a mês aparece no Meu dossiê, na seção "Aulas regulares".

### Corrigido (compra de fotos)

- A recusa da compra de fotos agora diz o motivo certo (teste do Gleyson, 17/10/2026): `FOTOS_DO_PROPRIO_FOTOGRAFO` quando quem compra é o fotógrafo do álbum, e `FOTOS_SO_DA_TURMA` só quando a pessoa não é da turma do curso; antes, os dois casos davam a mesma mensagem.

### Adicionado (página de pagamento)

- Página de pagamento única `/pagar/<código>` (17/10/2026): cada cobrança (inscrição em curso, pedido de fotos e mensalidade de aula regular) ganha um código sorteado que nunca se repete (ULID); a página mostra o resumo, o valor, o vencimento, o Pix (QR, copia e cola e identificador do extrato) e a situação, que se atualiza sozinha e vira "Pagamento confirmado"; só o dono da cobrança ou o Comando Geral abre (os outros recebem "não encontrado"); o e-mail de aviso da mensalidade e "Minhas aulas" levam direto a ela. Na carteirinha, categoria e nível iguais aparecem uma vez só.

### Adicionado (aulas regulares #80)

- Aulas regulares por assinatura (#80): o Comando Geral cadastra programas (Krav Maga, Kapap, Combate corpo a corpo...) com instrutor, mensalidade, divisão IBTO/instrutor (padrão 20/80), vagas, dia de vencimento e "só filiados", e as turmas (dias, horário, local ou link da sala de Meet/Zoom/Teams/YouTube); filiado e aluno indicado assinam em "Aulas regulares" e pagam pelo Pix em "Minhas aulas" (a tela se atualiza sozinha quando o IBTO confirma); a mensalidade é gerada 3 dias antes do vencimento, com aviso por e-mail; carência de 5 dias e depois só as aulas do programa travam (carteirinha e passaporte não mudam); confirmar lança a receita "Aulas regulares" e o repasse ao instrutor; o link da sala só aparece com o acesso liberado. A chamada e a frequência no dossiê vêm na próxima entrega.

### Alterado (logo e diagnóstico do piloto)

- O logo leva à home do site do IBTO para o visitante e ao painel para quem está logado (17/10/2026); antes, o visitante ia ao catálogo. Sem o endereço do site configurado, continua o catálogo.
- Estado do piloto no diagnóstico: o pós-deploy grava `storage/app/piloto-estado` (só sim/não e números, sem dado pessoal): config em cache, conta completa configurada/encontrada/montada, senha das contas de exemplo e stakeholders; o workflow de diagnóstico mostra esse arquivo.

### Adicionado (stakeholders do piloto)

- Stakeholders e contas de exemplo do piloto (17/10/2026): com `PILOTO_STAKEHOLDERS` no `.env` (nome, e-mail e papéis de cada um), cada stakeholder ganha conta com convite por e-mail, ficha de filiado, os papéis da função (admin de equipe com equipe [TESTE] própria) e a matrícula no evento "[TESTE] Evento do piloto: Operação Avançada (fotos)", que tem o álbum à venda ativo (a conta completa é a fotógrafa e sobe as fotos); com `PILOTO_SENHA_DEMO`, contas de exemplo de aluno, filiado, instrutor, admin de equipe e fotógrafo (`@demo.ib-to.org`), nunca de Comando Geral ou Tecnologia; nomes, e-mails e senha só no `.env`; roda pelo pós-deploy quando a lista muda e não duplica (`plataforma:piloto-stakeholders`).

### Adicionado (fotos pelo instrutor)

- O instrutor cuida das fotos do curso (Mestre Eduardo, 17/10/2026): na tela de Inscritos, o quadro "Fotos do curso" abre o álbum do evento (galeria gratuita para filiados, ligado ao curso, em rascunho até o Comando Geral publicar) e o álbum à venda (o instrutor é o fotógrafo; nasce desativado, 70% para ele, 60 dias); o instrutor envia, descreve e desfoca as fotos do curso dele, mas não publica; outro instrutor ou aluno recebe 403. A compra do aluno mostra os 3 passos (escolher, pagar o Pix, baixar).

### Removido

- Janelinha de usuário e senha (Basic Auth) da homologação (17/10/2026, início do piloto, pedido do Gleyson): cada pessoa indicada entra pela tela de login, com a própria conta; a homologação continua fora do Google (`X-Robots-Tag: noindex`); o deploy agora confere o noindex em vez da senha. Para voltar, reverter esta mudança.

### Adicionado

- Conta completa do piloto (pedido de 17/10/2026): com `PILOTO_CONTA_COMPLETA=<e-mail>` no `.env` da homologação, a conta ganha os papéis aluno, filiado, instrutor, admin de equipe e fotógrafo e dados [TESTE] em todos os módulos (matrícula paga, aguardando Pix, gratuita e concluída com certificado; curso como instrutor com alunos, chamada e repasse; solicitação de cadastro na equipe; álbum de fotos vendido e pedidos comprados); a ficha de filiado fica sem CPF, telefone ou nascimento; o e-mail nunca entra no código; roda uma vez pelo pós-deploy e não duplica (`plataforma:conta-completa`).

- Conteúdo extra do piloto (pedido de 17/10/2026): o Curso Tático Avançado do Course Ragnar Ukraine (23 e 24/01/2027, em rascunho até o IBTO pôr valor, local, vagas e o cartaz) e posts e álbuns [TESTE] de vários tipos para testar cenários (notícia, evento com pessoas autorizado, agendado, rascunho, em revisão e arquivado; álbum publicado, com foto sem descrição, com menores sem autorização dos responsáveis e arquivado), com imagens geradas, sem pessoas reais; comando `plataforma:carregar-conteudo-piloto`, que roda sozinho uma vez no servidor pela migration e não duplica.

- Menu "Fotos dos eventos" para o aluno (#90, decisão de 17/10/2026): lista os álbuns à venda da turma (só cursos com matrícula confirmada) e "Meus pedidos" (voltar ao Pix ou baixar os originais); aparece enquanto a loja de fotos estiver ligada; o botão na página do curso continua.

- Desfocar áreas das fotos da galeria (#90, pedido de 17/10/2026): no álbum, "Desfocar áreas" abre a foto para marcar retângulos (mouse, dedo ou teclado) sobre rostos, distintivos e placas; o servidor desfoca de verdade (a informação é descartada, não só escondida) e acerta a orientação das fotos de celular; o original fica numa pasta privada só para o Comando Geral, com "Restaurar original"; desfocar de novo parte sempre do original; apagar a foto apaga o original; tudo na auditoria.

- LGPD, anonimização a pedido do titular (#47): na ficha do filiado, só para quem cuida de todos os filiados, "Anonimizar a pedido do titular" pede a senha, o registro digitado de novo e o motivo; apaga nome, e-mail, senha, CPF, nascimento, telefone, cidade, fotos (os arquivos também), a autorização de imagem e o nome nos certificados; desativa a conta e invalida o QR da carteirinha; mantém o registro, a equipe, o nível, as presenças e o financeiro (sem nome); vai para a auditoria. Não tem volta.

- LGPD, política de privacidade e aceite (#47): página pública `/privacidade` (versão 0.1, rascunho em revisão pela diretoria e pelo jurídico, com aviso no topo e link no rodapé da vitrine): quais dados, para quê, proteção, compartilhamento, prazos e direitos; aceite registrado com versão e data, sem duplicar, e revogável (a revogação grava a data e vai para a auditoria); com `LGPD_EXIGIR_ACEITE=true` (desligado por padrão), quem não aceitou a versão atual é levado à política e volta para a tela que queria; trocar a versão pede o aceite de novo; o encarregado (DPO) aparece como "a definir" até a diretoria decidir (`LGPD_ENCARREGADO`).

- LGPD, registro de acesso (#47): toda abertura da ficha do filiado com CPF, nascimento e telefone vai para a auditoria (quem e quando, só o número do filiado); nova tela Configurações → Meus dados, onde a pessoa vê o que o IBTO guarda (dados sensíveis mascarados), a autorização de imagem, quem abriu a ficha dela (nome abreviado, data e hora) e como pedir correção, cópia ou exclusão.

- Passaporte digital do filiado (#81): ao ler o QR da carteirinha, o semáforo mostra 🟢 Liberado (ativo e em dia), 🟡 Liberado com ressalva (anuidade pendente, com o motivo) ou 🔴 Acesso negado (registro inativo ou suspenso); a regra fica num lugar só no servidor; o filiado vê o sinal atual e pode trocar o código do QR se ele vazar (o antigo para de valer na hora, auditado, até 3 trocas por hora).

### Corrigido

- E-mails da fila que falhavam com "Connection to smtp.titan.email has been closed unexpectedly" (visto às 01h00 de 17/10/2026): cada tarefa da fila agora abre uma conexão SMTP nova (o Titan fecha a conexão parada), e uma nova tentativa espera 1 minuto (`--backoff=60`), em vez de as 3 tentativas acontecerem no mesmo segundo.

### Alterado

- Loja de fotos dos eventos (#90) ligada por decisão do Gleyson (17/10/2026); o interruptor do Comando Geral continua desligando quando preciso, e só a turma do curso vê e compra.

### Adicionado

- Offline na plataforma (#74): dá para "instalar" na tela inicial do celular (manifesto com os ícones do IBTO); um service worker guarda o visual e as telas públicas abertas por visitante (catálogo, curso, trilha e blog) para abrir sem internet; tela com login nunca fica guardada no aparelho (LGPD, celular emprestado); sem internet e sem cópia, aparece a página "Sem internet"; em qualquer tela, um aviso avisa quando a conexão cai; cada publicação troca as cópias pela versão nova.
- Logs estruturados (#74): cada linha do log é um JSON com data, nível, código estável e o identificador do pedido (que também volta no cabeçalho `X-Request-Id` de toda resposta); uma máscara esconde CPF, e-mail, telefone, nascimento, endereço, senha e token antes de gravar, mesmo se alguém esquecer; padrão de um arquivo por dia, guardado por 14 dias (`LOG_STACK=daily`).
- SEO das telas públicas (#74): catálogo, curso e trilha já saem do servidor com descrição, endereço oficial (canônico), cartão do link para WhatsApp e redes (Open Graph, com a capa do curso) e dados estruturados "Course" (schema.org); `/sitemap.xml` com o catálogo, os cursos publicados abertos e as trilhas ativas; prévia, curso fechado e as validações de certificado e de carteirinha levam "noindex" (têm nome de pessoa), e o `robots.txt` foi ajustado.
- VLibras em todas as telas da plataforma (#74): o widget oficial do governo (tradução em Libras) fica no HTML base, fora do React, e continua aberto ao trocar de tela; afastado do entalhe do celular (safe area); some na impressão; sem internet, simplesmente não aparece.

- Carteirinha do filiado com QR code (#46): "Minha carteirinha" no menu, no tamanho de cartão para imprimir ou salvar em PDF (foto 3x4, registro, nível, equipe, validade 31/12); cada filiado ganha um código sorteado (`IBTO-XXXX-XXXX`); a validação pública mostra só o nome abreviado, a situação (ativa, anuidade pendente ou inativa), categoria, nível e equipe; nome completo, cidade e foto só com a autorização de imagem; código inexistente não revela nada; limite de consultas por IP. O novo certificado de graduação aguarda a decisão do Mestre Eduardo sobre o número de série.

## [0.2.0] — 2026-10-17 (homologação: piloto completo)

Base do piloto com os cursos reais, Pix copia e cola, cupom, pré-requisitos, fotos, venda de fotos (desligada), Meu painel, relatórios, cadastro fechado e e-mails com a marca.

### Adicionado

- Versões e releases (#75): arquivo `VERSION` (versionamento semântico); versão e commit no rodapé; `/api/saude` e o console do navegador (F12) mostram o back (versão e commit), o front (data do build) e o banco (versão e última migration); a CI recusa PR de código sem linha no CHANGELOG; ao criar a tag `vX.Y.Z`, a release sai sozinha com o pacote da HostGator, o hash SHA-256 e as notas do CHANGELOG.
- Módulo de relatórios (#73): filiados por equipe (com anuidade), cursos (inscritos, receita, repasse e parte do IBTO), frequência e certificados, financeiro por categoria e auditoria; uma permissão por relatório; filtro De/Até; CSV e versão para impressão/PDF com a marca do IBTO, a data e quem gerou; toda exportação vai para a auditoria; nenhum dado pessoal sensível. A geração em fila para relatórios muito grandes fica para quando o volume pedir.
- Meu painel do instrutor e do admin de equipe (#79): por curso, inscritos por situação, comparecimento (presenças ÷ confirmados × encontros com chamada), a receber e recebido (a parte do instrutor no livro-caixa) e certificados (com os aptos ainda sem certificado); totais do período, filtro De/Até e exportar CSV; o instrutor vê só os dele e o admin de equipe só os da equipe; nenhum dado pessoal de aluno.
- Venda de fotos dos eventos (#90), começando DESLIGADA: interruptor no Comando Geral; álbum por curso com fotógrafo credenciado (papel novo "Fotógrafo"), preço por foto, parte do fotógrafo e expiração; envio em lote com original privado e prévia com marca d'água (emblema do IBTO + nome do fotógrafo); só a turma (matrícula confirmada) vê e compra; Pix copia e cola com identificador IBTOFOT; confirmar lança a receita "Venda de fotos" e o repasse "Fotógrafos"; download do original por 30 dias; limpeza diária apaga as fotos vencidas; "Meu financeiro" do fotógrafo; manual do Fotógrafo.
- Cupom promocional nos cursos (#89): tela "Cupons" (Financeiro) para criar códigos com desconto percentual ou fixo, validade de/até, limite total e por pessoa, só filiados e cursos em que vale; o aluno digita na inscrição e o Pix já sai com o valor descontado (100% confirma na hora); recusas com código (`CUPOM_INVALIDO`, `CUPOM_VENCIDO`, `CUPOM_ESGOTADO`…); limite à prova de corrida; desconto antes da divisão IBTO/instrutor; permissão `cupons.gerenciar` (Comando Geral e Tecnologia).
- Pré-requisitos dos cursos (#88): curso anterior (basta um concluído, com certificado válido), nível mínimo e tempo mínimo de filiado; a página do curso mostra cada item com ✅ ou ❌ e bloqueia a inscrição de quem não cumpre (o servidor também recusa, `MATRICULA_REQUISITO_PENDENTE`); o Comando Geral matricula por exceção só com motivo, gravado na matrícula e auditado; campo "Data de filiação ao IBTO" na ficha do filiado.
- Base do piloto (`plataforma:carregar-piloto`): os cursos reais dos cartazes com a data real (Encerrado ou Rascunho), incluindo Operação Avançada e as três turmas do Treinamento de Combate Real em Piracicaba-SP, e o cartaz como capa, uma turma [TESTE] publicada de cada, 2 cursos online [TESTE] (gravado e ao vivo) e a trilha "Formação Operacional"; roda no servidor uma vez com `PILOTO_CARREGAR=true`, sem duplicar e sem criar contas.
- Pix copia e cola com QR code (#83): na inscrição, o aluno vê o QR code e o botão "Copiar código Pix", com o valor exato e o identificador da matrícula (ex.: `IBTOMAT1234`) já dentro do código, gerado na própria plataforma pelo padrão BR Code do Banco Central (sem banco nem contrato); aviso para conferir o nome de quem recebe no app do banco; o identificador aparece na lista e na ficha de matrículas do Comando Geral para conferir no extrato.
- Foto de perfil e foto 3x4 do filiado (#78): enviadas em Configurações → Perfil ou na ficha do filiado; recortadas em quadrado, reduzidas para 400 px e gravadas em WebP numa pasta privada; aparecem no menu da conta, nos inscritos e na chamada só para quem pode ver; autorização de imagem para a Rede Nacional, desmarcada por padrão, com data e auditoria.
- Blog e eventos mostrados no site `ib-to.org`: API pública do post inteiro e da galeria, paginação e filtro por categoria; as páginas `/blog` e `/eventos` da plataforma levam ao site (#61, PR #33).
- CORS das imagens para o modo offline do site; `robots.txt` e `llms.txt` da plataforma (#61, PR #33).
- Tour guiado nas telas principais (#68): abre sozinho na primeira visita de cada tela, botão "?" para rever, interruptor "Tour guiado" no menu da conta (salvo na conta), textos em português, teclado e leitor de tela.
- Sala ao vivo nos encontros online (#71): campo "Link da sala ao vivo" na aula (só Google Meet, Zoom, Teams ou YouTube, por https); o aluno confirmado vê "Entrar na sala" de 30 minutos antes até o fim do encontro; quem organiza vê sempre; o link nunca sai no catálogo nem na API.
- Página Ajuda com o manual de instruções de cada perfil (#68): aluno e filiado, instrutor, admin de equipe, Comando Geral e Tecnologia; cada seção com passos, regras e o porquê, busca, "Mostrar na tela" (abre a tela com o tour) e impressão ou PDF. Administrador e Tecnologia veem todos os manuais.
- Certificado automático do curso gravado (#72): exige 100% das aulas gravadas assistidas e sai sozinho ao marcar a última (uma vez só, sem "emitido por", auditado); curso com encontros continua com 75% de presença e emissão pelo IBTO; curso misto exige as duas coisas.
- Cadastro com aprovação (#84; grupo fechado): "Criar conta" virou "Pedir acesso", com equipe e quem indicou; a conta nasce em análise (trava no servidor) e o admin da equipe ou o Comando Geral aprova (vira Aluno, e-mail) ou recusa com motivo (e-mail); CADASTRO_PUBLICO=fechado deixa só o convite.
- E-mails com a marca do IBTO: emblema e nome no topo, cores da plataforma, texto próprio na confirmação (endereço oficial e prazo do link) e, no rodapé de todos, o aviso de segurança contra golpes ("o IBTO nunca pede senha, códigos ou dados bancários"; confira o endereço antes de clicar).

### Alterado

- Fotos dos eventos privadas por padrão: só filiados e cargos acima as veem, com login (muitos alunos e instrutores são operadores de segurança); visitante vai para a tela de entrar, aluno sem filiação vê o aviso, e a API do site entrega o evento sem fotos, com o link para entrar. O blog continua público. `GALERIA_PUBLICA=true` volta ao modo aberto.
- O cadastro público começa **fechado** (só convite) por padrão; a tela "Pedir acesso" só aparece enquanto a chave de partida (`ADMIN_EMAILS`) estiver preenchida, e só aceita aqueles e-mails, que entram já aprovados e sem precisar de equipe.

### Segurança

- LGPD: a conta logada passa a ir para as telas sem relacionamentos carregados, e o filiado esconde CPF, nascimento, telefone e o hash do CPF sempre que vira dado de tela. Antes, se a requisição já tinha consultado o filiado da pessoa, os dados pessoais dela iam junto no código da página (só para ela mesma).
- Com o endereço oficial em https, todo link gerado pela plataforma (e-mails, botões, QR codes) sai com https.

### Corrigido

- Carga do piloto: curso gratuito entra como "só filiados" (regra do IBTO); antes entrava aberto ao público.
- Curso cancelado some do site em até 1 minuto (a lista de cursos da API pública ficava 5 minutos guardada e o link levava a 404).
- Deploy da homologação sem erro 500 no meio da troca de versão: o deploy põe a placa "em atualização" (503) antes de enviar o código e o Cron a tira depois das migrations e dos caches; novo job "Diagnóstico homologação" mostra os erros do log do servidor, sem dados pessoais.
- Página Ajuda com o menu lateral e o topo duplicados: a tela desenhava o layout de novo; agora usa o layout do app, e um teste barra telas que importem o layout principal.

## [0.1.0] — 2026-10-15 (homologação)

Primeira versão completa para o piloto, no ar em `cursos-homolog.ib-to.org`.

### Adicionado

- API pública das áreas de atuação (#57, PR #32) e dos cursos (#52, PR #31) para o site.
- API pública do blog com CORS só para o site do IBTO (#61, PR #29).
- Galeria de eventos: álbuns com descrição, crédito e autorização de imagem (#61, PR #28).
- Botão "Suporte técnico" pelo WhatsApp com mensagem padronizada (PR #27).
- Blog: notícias e eventos com regras de publicação no Comando Geral e páginas públicas (#61, PR #25).
- Certificado de curso no modelo institucional do Mestre Eduardo (PR #23).
- Situação de cada aula, barra de vagas e resumo do dossiê (desenhos de 10/10, PR #22).
- Matrículas com PIX, certificados com QR code, curso fechado, divisão do valor, progresso das aulas e Meu dossiê (#41, #44, PR #18).
- Filiados: importar mesmo assim e telas sem rolagem lateral (#37, PR #17).
- Usuários: desativar e reativar, nova senha e convite (#36, PR #16).
- Identidade visual do IBTO: cores, logo e fontes (PR #15).
- Cursos, trilhas, conteúdo e chancela (#38, #39, #40, PR #13).
- Financeiro: livro-caixa, anuidade, parcelas, cancelamento e filtros por período (#59, #37, PRs #11 e #12).
- Login reforçado e papel Aluno automático no cadastro (#36, PR #10).
- Papéis e permissões no molde da CBSA (#36, PR #7).
- Filiados: cadastro, convite, limite por equipe e importação (#37, PR #8).
- Cadastros de áreas de atuação, níveis e equipes (#37, PR #5).
- Menu de idiomas pelo Google Tradutor (#53, PR #4).
- Publicação automática em `cursos-homolog.ib-to.org` (#35, PR #2).
- Base da plataforma: Laravel 13, React, rota de saúde e CI (#35, PR #1).

### Corrigido

- `/api/publico` e `/imagens` sem a senha da homologação, para o site de testes ler o blog (PR #30).
- Janelas que abrem por cima da tela não passam da área segura (PR #26); área segura em todas as telas (PR #14).
- Lista de cursos troca tabela por cartões em 1024 px (PR #24).
- Telas e mensagens em português; menu sem os links do kit (PRs #20 e #21).
- Nenhum erro em silêncio, no servidor e nas telas (#58, PR #9).
- `VERSAO_DEPLOY` gravado por último e log sem dados pessoais (PR #6).
- Pendências da homologação: pós-deploy, vendor em .zip, e-mail pela fila (PR #3).

[Não lançado]: https://github.com/atnzpe/ibto-plataforma/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/atnzpe/ibto-plataforma/releases/tag/v0.1.0
