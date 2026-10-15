<?php

/*
 * CORS ("compartilhamento entre sites"): quais OUTROS sites podem ler as
 * respostas do /api da plataforma pelo navegador. Sem esta regra, o padrão do
 * Laravel deixaria QUALQUER site ler.
 *
 * Quem pode (issue #52 e blog #61, 15/10/2026): só o site do IBTO — o oficial
 * (ib-to.org) e o de testes (homolog.ib-to.org). A lista vem do .env
 * (CORS_ORIGENS, separados por vírgula); sem nada no .env, vale este padrão.
 * Só leitura (GET): nenhum site de fora consegue gravar nada aqui.
 */

return [

    // Só os endereços públicos e de leitura (/api/publico/...) entram nesta regra.
    'paths' => ['api/publico/*'],

    // Só leitura.
    'allowed_methods' => ['GET'],

    // Os sites que podem ler. Ex.: CORS_ORIGENS=https://ib-to.org,https://homolog.ib-to.org
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ORIGENS', 'https://ib-to.org,https://www.ib-to.org,https://homolog.ib-to.org')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type'],

    'exposed_headers' => [],

    // O navegador guarda esta autorização por 1 hora (menos pedidos de conferência).
    'max_age' => 3600,

    // Sem cookies nem login entre sites: a leitura é anônima.
    'supports_credentials' => false,

];
