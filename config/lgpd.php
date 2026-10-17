<?php

/*
 * CONFIGURAÇÃO DA LGPD (#47): a versão da política de privacidade em vigor, se o
 * aceite é obrigatório e quem é o encarregado de dados (DPO).
 *
 * Trocar a VERSÃO (ex.: '0.1' → '1.0') faz todo mundo precisar aceitar de novo
 * (quando o aceite estiver ligado), porque o texto mudou.
 */

return [

    // A versão do texto publicado em /privacidade. Ex.: '0.1' (rascunho em revisão).
    'politica_versao' => '0.1',

    // A data desta versão (aparece no topo da política). Ex.: '17/10/2026'.
    'politica_data' => '17/10/2026',

    // true = quem entra precisa aceitar a versão atual antes de usar a plataforma.
    // Começa DESLIGADO: o texto ainda é rascunho e depende da diretoria e do jurídico.
    // Para ligar, no .env do servidor: LGPD_EXIGIR_ACEITE=true.
    'exigir_aceite' => (bool) env('LGPD_EXIGIR_ACEITE', false),

    // O encarregado de dados (DPO): decisão do Mestre Eduardo. Vazio = "a definir".
    'encarregado' => env('LGPD_ENCARREGADO'),

    // Para onde vão os pedidos do titular (correção, cópia, exclusão).
    'contato' => env('LGPD_CONTATO', 'suporte@ib-to.org'),

];
