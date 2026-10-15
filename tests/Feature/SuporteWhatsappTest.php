<?php

/*
 * Testes do número do SUPORTE TÉCNICO (SUPORTE_WHATSAPP no .env, 15/10/2026):
 * chega às telas só com números; vazio, chega null (o botão não aparece).
 * Número INVENTADO.
 */

test('o número do suporte chega às telas só com números', function () {
    // Como o config/app.php limpa o valor do .env (tira espaços, traços, parênteses).
    config(['app.suporte_whatsapp' => preg_replace('/\D/', '', '+55 (81) 99999-0000')]);

    $this->get(route('catalogo.index'))
        ->assertInertia(fn ($tela) => $tela->where('suporte_whatsapp', '5581999990000'));
});

test('sem número no .env, chega null e o botão não aparece', function () {
    config(['app.suporte_whatsapp' => '']);

    $this->get(route('catalogo.index'))
        ->assertInertia(fn ($tela) => $tela->where('suporte_whatsapp', null));
});
