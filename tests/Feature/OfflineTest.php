<?php

/*
 * Testes do OFFLINE (#74): o catálogo do visitante leva a marca que deixa o
 * service worker guardar a tela; com login, NUNCA (LGPD: celular emprestado);
 * o HTML traz o manifesto, o aviso "sem internet" e o registro do service
 * worker com a versão; os arquivos do PWA existem.
 */

use App\Models\User;

test('o catálogo do visitante pode ser guardado; com login, nunca', function () {
    $this->get(route('catalogo.index'))->assertOk()->assertHeader('X-Ibto-Offline', 'publico');

    $this->actingAs(User::factory()->create())->get(route('catalogo.index'))
        ->assertOk()
        ->assertHeaderMissing('X-Ibto-Offline');
});

test('tela com login não leva a marca de offline', function () {
    $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertHeaderMissing('X-Ibto-Offline');
});

test('o HTML traz o manifesto, o aviso sem internet e o service worker com a versão', function () {
    $this->get(route('catalogo.index'))
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
        ->assertSee('id="aviso-offline"', false)
        ->assertSee("register('/sw.js?v=".urlencode(config('app.versao').'-'.config('app.commit'))."')", false);
});

test('os arquivos do PWA existem e o manifesto é um JSON válido', function () {
    expect(public_path('sw.js'))->toBeFile()
        ->and(public_path('offline.html'))->toBeFile()
        ->and(public_path('icons/icon-512.png'))->toBeFile();

    $manifesto = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);
    expect($manifesto['start_url'])->toBe('/cursos')->and($manifesto['icons'])->toHaveCount(3);
});
