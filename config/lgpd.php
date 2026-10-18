<?php

/*
 * CONFIGURAÇÃO DA LGPD (#47): a versão da política de privacidade em vigor, se o
 * aceite é obrigatório e quem é o encarregado de dados (DPO).
 *
 * Trocar a VERSÃO (ex.: '0.1' → '1.0') faz todo mundo precisar aceitar de novo
 * (quando o aceite estiver ligado), porque o texto mudou.
 */

return [

    // A versão do texto publicado em /privacidade. 1.0 = APROVADA pela diretoria
    // (Gleyson, 18/10/2026). Antes: '0.1' (rascunho em revisão).
    'politica_versao' => '1.0',

    // A data desta versão (aparece no topo da política).
    'politica_data' => '18/10/2026',

    // true = quem entra precisa aceitar a versão atual antes de usar a plataforma.
    // Com o texto aprovado (1.0), pode ligar: no .env do servidor, LGPD_EXIGIR_ACEITE=true.
    'exigir_aceite' => (bool) env('LGPD_EXIGIR_ACEITE', false),

    // O encarregado de dados (DPO), definido pela diretoria em 18/10/2026. O nome fica
    // SÓ no .env do servidor (regra do projeto: nomes de pessoas fora do código).
    // Vazio = "a definir".
    'encarregado' => env('LGPD_ENCARREGADO'),

    // Para onde vão os pedidos do titular (correção, cópia, exclusão).
    'contato' => env('LGPD_CONTATO', 'suporte@ib-to.org'),

];
