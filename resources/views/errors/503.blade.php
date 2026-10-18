<!DOCTYPE html>
{{--
    TELA DE MANUTENÇÃO (503) DO IBTO (17/10/2026).
    Aparece enquanto o deploy atualiza a plataforma (a "placa" posta pelo deploy, que
    a tarefa agendada tira em alguns minutos) ou num "php artisan down". Antes era a
    página genérica "503 Service Unavailable", que parecia erro e assustava quem testa.
    Tudo embutido (estilo e textos), sem os arquivos do front: no meio do deploy eles
    podem estar sendo trocados. A página se recarrega sozinha a cada 60 segundos.
--}}
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
            main { max-width: 30rem; }
            img { width: 96px; height: 96px; }
            h1 { color: #c9bd94; font-size: 1.5rem; margin: 1rem 0 0.5rem; }
            p { line-height: 1.5; }
            .pequeno { color: #b9b4a8; font-size: 0.9rem; }
        </style>
    </head>
    <body>
        <main>
            <img src="/images/ibto-emblema.png" alt="Emblema do IBTO">
            <h1>Estamos atualizando a plataforma</h1>
            <p>Volte em alguns minutos. Nada foi perdido: seus dados e o que você estava fazendo continuam guardados.</p>
            <p class="pequeno">Esta página se recarrega sozinha. Se demorar mais de 20 minutos, use o suporte técnico do IBTO.</p>
        </main>
    </body>
</html>
