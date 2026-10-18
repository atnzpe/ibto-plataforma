<?php

/*
 * Teste da LIMPEZA DO LINK DE GRUPO (18/10/2026): a migração tira da descrição
 * dos cursos o convite de grupo do WhatsApp e mantém o resto do texto.
 */

use App\Models\Curso;

test('a migração troca o convite de grupo por uma frase neutra e mantém o resto', function () {
    $curso = Curso::create(['titulo' => 'Curso X', 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 0,
        'descricao' => "Dois dias.\n\nInscrições e dúvidas pelo grupo do WhatsApp: https://chat.whatsapp.com/AbC123xyz"]);

    (require database_path('migrations/2026_10_18_000005_tira_link_do_grupo_da_descricao.php'))->up();

    expect($curso->fresh()->descricao)->toContain('Dois dias.')->not->toContain('chat.whatsapp.com');
});
