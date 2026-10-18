<?php

/*
 * Testes da TELA DE MANUTENÇÃO (17/10/2026): com a placa do deploy, quem abre a
 * plataforma vê a mensagem do IBTO ("Estamos atualizando...") com o código 503,
 * e não a página genérica em inglês.
 */

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::delete(storage_path('framework/down'));
});

test('com a placa do deploy, aparece a tela do IBTO em português (503)', function () {
    // A mesma placa que o deploy grava (motivo deploy-homologacao, sem modelo próprio).
    File::put(storage_path('framework/down'), json_encode(['except' => [], 'redirect' => null, 'retry' => 60, 'refresh' => 60, 'secret' => null, 'status' => 503, 'template' => null, 'motivo' => 'deploy-homologacao']));

    $this->get('/login')
        ->assertStatus(503)
        ->assertSee('Estamos atualizando a plataforma')
        ->assertSee('um minuto de treino')
        ->assertDontSee('Service Unavailable');
});

test('a mensagem de treino vem sorteada e com a fonte (ou IBTO)', function () {
    File::put(storage_path('framework/down'), json_encode(['status' => 503, 'template' => null]));

    $html = $this->get('/')->assertStatus(503)->getContent();

    // Toda mensagem termina com a fonte (Sun Tzu, um ditado ou o próprio IBTO).
    expect($html)->toMatch('/<cite id="fonte">— \S/');
});
