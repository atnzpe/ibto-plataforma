<?php

/*
 * DADOS INSTITUCIONAIS DO IBTO usados nos documentos (certificados), com a
 * chance de ajustar pelo .env do servidor sem mexer no código.
 * São dados PÚBLICOS da instituição (impressos nos certificados oficiais).
 */

return [

    // Local impresso no certificado de graduação (#46). Ex.: 'Rio de Janeiro - RJ'.
    'certificado_local' => env('IBTO_CERTIFICADO_LOCAL', 'Rio de Janeiro - RJ'),

    // Quem assina os certificados de graduação: o nome e o cargo (modelo do Mestre Eduardo).
    'presidente_nome' => env('IBTO_PRESIDENTE_NOME', 'Eduardo Lopes'),
    'vice_nome' => env('IBTO_VICE_NOME', 'Julio C. Moura Jr.'),

];
