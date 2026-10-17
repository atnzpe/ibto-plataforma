<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        {{-- viewport-fit=cover: a página ocupa a tela INTEIRA do celular, inclusive em volta
             do entalhe (notch) e da barra de gestos. As margens de segurança (SafeArea, pedido
             do Gleyson em 10/10/2026) ficam no app.css, para nada ficar escondido atrás deles. --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        {{-- noindex: antes do lançamento (APP_INDEXAVEL=false) ou numa tela que nunca deve ir
             para o Google (ex.: a validação da carteirinha, #46, que mostra um nome abreviado). --}}
        @if (! config('app.indexavel') || ! empty($noindex))
            <meta name="robots" content="noindex, nofollow">
        @endif

        {{-- SEO DA TELA PÚBLICA (#74): só quando o controller mandou ($seo vem do App\Support\Seo).
             Sai pronto no HTML porque o Google e o WhatsApp leem a página antes do React. --}}
        @isset($seo)
            <meta name="description" content="{{ $seo['descricao'] }}">
            {{-- O endereço oficial da página (sem os filtros da busca). --}}
            <link rel="canonical" href="{{ $seo['url'] }}">
            {{-- Open Graph: o cartão que aparece ao colar o link no WhatsApp, Facebook, LinkedIn. --}}
            <meta property="og:type" content="{{ $seo['tipo'] }}">
            <meta property="og:site_name" content="{{ config('app.name') }}">
            <meta property="og:locale" content="pt_BR">
            <meta property="og:title" content="{{ $seo['titulo'] }}">
            <meta property="og:description" content="{{ $seo['descricao'] }}">
            <meta property="og:url" content="{{ $seo['url'] }}">
            <meta property="og:image" content="{{ $seo['imagem'] }}">
            <meta name="twitter:card" content="summary_large_image">
            {{-- Dados estruturados (JSON-LD): "isto é um curso do IBTO". O texto já vem
                 protegido (JSON_HEX_TAG), por isso pode ir sem o escape do Blade. --}}
            @if ($seo['json_ld'])
                <script type="application/ld+json">{!! $seo['json_ld'] !!}</script>
            @endif
        @endisset

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #faf8f3; {{-- mesmo fundo do modo claro (app.css) --}}
            }

            html.dark {
                background-color: #0f0e0b; {{-- mesmo fundo do modo escuro (app.css) --}}
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        {{-- Cor da barra do navegador no celular: o preto da marca IBTO (igual ao site). --}}
        <meta name="theme-color" content="#0a0a0a">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />

        {{-- VLIBRAS (#74): o widget oficial do governo (vlibras.gov.br), que traduz a tela
             para a Língua Brasileira de Sinais (Libras). Fica FORA do React (do <x-inertia::app />)
             porque ele monta a própria parte da página: assim continua aberto quando a pessoa
             troca de tela. É o mesmo trecho do site. --}}
        <div vw class="enabled">
            <div vw-access-button class="active"></div>
            <div vw-plugin-wrapper>
                <div class="vw-plugin-top-wrapper"></div>
            </div>
        </div>
        {{-- defer: o script do governo só roda depois que a página já apareceu (não atrasa a tela). --}}
        <script src="https://vlibras.gov.br/app/vlibras-plugin.js" defer></script>
        <script>
            // Liga o widget quando a página termina de carregar. Sem internet (ou se o gov.br
            // estiver fora do ar), o script acima não carrega: aí o widget simplesmente não
            // aparece, e a plataforma continua funcionando normalmente.
            window.addEventListener('load', function () {
                if (window.VLibras) new window.VLibras.Widget('https://vlibras.gov.br/app');
            });
        </script>
    </body>
</html>
