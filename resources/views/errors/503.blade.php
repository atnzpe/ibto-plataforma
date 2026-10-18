<!DOCTYPE html>
{{--
    TELA DE MANUTENÇÃO (503) DO IBTO (17/10/2026).
    Aparece enquanto o deploy atualiza a plataforma (a "placa" posta pelo deploy, que
    a tarefa agendada tira em alguns minutos) ou num "php artisan down". Antes era a
    página genérica "503 Service Unavailable", que parecia erro e assustava quem testa.

    Pedido do Gleyson: a espera vira um momento de treino. O emblema "respira" (pulso
    suave) e uma de 40 mensagens aparece, trocando a cada 15 segundos:
    - A Arte da Guerra (Sun Tzu, domínio público): tradução livre do IBTO, com a fonte;
    - mentalidade operacional e "homem cinza" (textos próprios do IBTO);
    - treino e comunicação (textos próprios do IBTO, sem copiar livros com direitos autorais);
    - mindfulness: exercícios curtos de respiração, calma e foco (textos próprios do IBTO).
    Tudo embutido (estilo, textos e script), sem os arquivos do front: no meio do deploy
    eles podem estar sendo trocados. A página se recarrega sozinha a cada 60 segundos.
--}}
@php
    // As 40 mensagens: [texto, fonte]. Fonte vazia = texto do IBTO.
    $mensagens = [
        // A Arte da Guerra (Sun Tzu), em tradução livre do IBTO.
        ['Conheça o adversário e conheça a si mesmo: em cem combates, você não correrá perigo.', 'Sun Tzu, A Arte da Guerra'],
        ['A suprema arte da guerra é vencer o inimigo sem precisar lutar.', 'Sun Tzu, A Arte da Guerra'],
        ['Quem se prepara e espera o adversário despreparado vence.', 'Sun Tzu, A Arte da Guerra'],
        ['Na paz, prepare-se para o conflito; no conflito, prepare-se para a paz.', 'Sun Tzu, A Arte da Guerra (tradução livre)'],
        ['Seja rápido como o vento, calmo como a floresta e firme como a montanha.', 'Sun Tzu, A Arte da Guerra'],
        ['A oportunidade de vencer o inimigo é dada pelo próprio inimigo; a de não ser vencido depende de você.', 'Sun Tzu, A Arte da Guerra'],
        ['Em meio ao caos, também há oportunidade.', 'Sun Tzu, A Arte da Guerra'],
        ['O guerreiro vitorioso vence primeiro e depois vai à guerra; o derrotado vai à guerra e depois procura vencer.', 'Sun Tzu, A Arte da Guerra'],
        ['Disciplina e ordem valem mais do que o número de soldados.', 'Sun Tzu, A Arte da Guerra (tradução livre)'],
        ['Planeje o difícil enquanto ele ainda é fácil.', 'Sun Tzu, A Arte da Guerra (tradução livre)'],
        // Mentalidade operacional e "homem cinza" (textos do IBTO).
        ['Homem cinza: chamar atenção é perder a vantagem. Roupas discretas, postura neutra, olhos atentos.', ''],
        ['Ao entrar num ambiente, localize as saídas antes de procurar um lugar para sentar.', ''],
        ['Consciência situacional não é medo: é saber o que é normal para perceber o que não é.', ''],
        ['Sente-se de onde você enxerga a porta. É um hábito que não custa nada.', ''],
        ['Rotina previsível é informação de graça para quem observa. Varie caminhos e horários.', ''],
        ['Na dúvida, distância. Tempo e espaço são as melhores defesas.', ''],
        ['O melhor confronto é o que você percebeu cedo e evitou.', ''],
        ['Discrição também é segurança: o que você não posta não pode ser usado contra você.', ''],
        ['Sob estresse, você não sobe ao nível da expectativa: desce ao nível do treino.', 'Ditado militar (atribuído a Arquíloco)'],
        ['Respire em quatro tempos: inspira, segura, expira, segura. Mente calma decide melhor.', ''],
        // Treino e comunicação (textos do IBTO).
        ['Aproveite estes minutos: revise de cabeça a última técnica que você estudou.', ''],
        ['Lento é suave, suave é rápido. Repita devagar até ficar automático.', 'Ditado das forças especiais'],
        ['Pratique a guarda agora: queixo baixo, mãos à frente, base firme.', ''],
        ['Uma repetição perfeita vale mais do que dez apressadas.', ''],
        ['Anote três coisas que você aprendeu no último curso. O que é escrito é lembrado.', ''],
        ['Primeira impressão conta: olhe nos olhos, cumprimente com firmeza e escute antes de falar.', ''],
        ['Quem escuta com atenção entende o problema antes de propor a solução.', ''],
        ['Fale pouco, com calma e voz firme. Em tensão, a sua calma contagia.', ''],
        ['Pergunte o nome e use o nome: as pessoas cooperam com quem as reconhece.', ''],
        ['Disciplina é fazer o certo também quando ninguém está olhando.', ''],
        // Mindfulness (atenção plena): exercícios curtos de calma e foco (textos do IBTO).
        ['Pare por um instante: sinta os pés no chão e o peso do corpo na cadeira.', ''],
        ['Respire pelo nariz contando até quatro e solte pela boca contando até seis. Repita três vezes.', ''],
        ['Nomeie em silêncio cinco coisas que você vê, quatro que ouve e três que sente na pele.', ''],
        ['Solte os ombros, relaxe a mandíbula e afaste a língua do céu da boca. Tensão gasta energia.', ''],
        ['Perceba o pensamento que está passando agora. Não brigue com ele: só observe e volte à respiração.', ''],
        ['Atenção plena é estar inteiro no que você faz: no treino, na conversa, na espera.', ''],
        ['Antes de reagir, respire uma vez. Esse segundo é a diferença entre reação e decisão.', ''],
        ['Feche os olhos por três respirações e escute o som mais distante que conseguir.', ''],
        ['Ansiedade olha para o futuro; o treino acontece agora. Traga a mente de volta para este minuto.', ''],
        ['Termine esta espera com uma intenção simples para o resto do dia. Uma só.', ''],
    ];
    // A primeira mensagem já vem sorteada do servidor (funciona mesmo sem JavaScript).
    $primeira = $mensagens[array_rand($mensagens)];
@endphp
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="robots" content="noindex">
        {{-- Recarrega sozinha a cada 60 s: quando a atualização termina, a tela certa aparece. --}}
        <meta http-equiv="refresh" content="60">
        <meta name="theme-color" content="#0a0a0a">
        <title>Atualizando · IBTO</title>
        <style>
            /* Cores da marca (preto equilibrado e dourado), com margens de segurança (notch). */
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #14161a;
                color: #f3f1ec;
                font-family: system-ui, sans-serif;
                padding: max(16px, env(safe-area-inset-top)) max(16px, env(safe-area-inset-right)) max(16px, env(safe-area-inset-bottom)) max(16px, env(safe-area-inset-left));
                box-sizing: border-box;
                text-align: center;
            }
            main { max-width: 34rem; }
            /* O emblema "respira": cresce e brilha devagar (4 s por ciclo). */
            img { width: 112px; height: 112px; animation: respira 4s ease-in-out infinite; }
            @keyframes respira {
                0%, 100% { transform: scale(1); filter: drop-shadow(0 0 0 rgba(201, 189, 148, 0)); }
                50% { transform: scale(1.06); filter: drop-shadow(0 0 18px rgba(201, 189, 148, 0.45)); }
            }
            /* Quem pediu menos movimento no aparelho: emblema parado. */
            @media (prefers-reduced-motion: reduce) { img { animation: none; } }
            h1 { color: #c9bd94; font-size: 1.4rem; margin: 1rem 0 0.25rem; }
            .aviso { color: #b9b4a8; font-size: 0.95rem; margin: 0 0 1.75rem; }
            /* A mensagem de treino, num cartão. */
            blockquote {
                margin: 0;
                padding: 1.25rem 1.25rem 1rem;
                border: 1px solid rgba(201, 189, 148, 0.35);
                border-radius: 0.75rem;
                background: #1d2025;
                transition: opacity 0.6s ease;
            }
            blockquote p { font-size: 1.1rem; line-height: 1.55; margin: 0 0 0.6rem; }
            blockquote cite { color: #c9bd94; font-size: 0.85rem; font-style: normal; }
            .pequeno { color: #8f8a7e; font-size: 0.8rem; margin-top: 1.5rem; }
        </style>
    </head>
    <body>
        <main>
            <img src="/images/ibto-emblema.png" alt="Emblema do IBTO">
            <h1>Estamos atualizando a plataforma</h1>
            <p class="aviso">Volte em alguns minutos. Enquanto isso, um minuto de treino:</p>
            {{-- aria-live: o leitor de tela anuncia a mensagem nova quando ela troca. --}}
            <blockquote id="mensagem" aria-live="polite">
                <p id="texto">{{ $primeira[0] }}</p>
                <cite id="fonte">{{ $primeira[1] !== '' ? '— '.$primeira[1] : '— IBTO' }}</cite>
            </blockquote>
            <p class="pequeno">Esta página se recarrega sozinha. Se demorar mais de 20 minutos, use o suporte técnico do IBTO.</p>
        </main>
        <script>
            // Troca a mensagem a cada 15 s, sem repetir a anterior (textos fixos do servidor).
            (function () {
                var mensagens = @json($mensagens);
                var bloco = document.getElementById('mensagem');
                var texto = document.getElementById('texto');
                var fonte = document.getElementById('fonte');
                var atual = mensagens.findIndex(function (m) { return m[0] === texto.textContent; });
                setInterval(function () {
                    var proxima = atual;
                    // Sorteia até cair numa diferente da que está na tela.
                    while (proxima === atual) { proxima = Math.floor(Math.random() * mensagens.length); }
                    atual = proxima;
                    bloco.style.opacity = '0';
                    setTimeout(function () {
                        texto.textContent = mensagens[atual][0];
                        fonte.textContent = '— ' + (mensagens[atual][1] || 'IBTO');
                        bloco.style.opacity = '1';
                    }, 600);
                }, 15000);
            })();
        </script>
    </body>
</html>
