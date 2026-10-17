<?php

/*
 * Testes do VLIBRAS (#74): o widget oficial do governo (tradução em Libras) vem
 * no HTML base, então aparece em TODAS as telas: as públicas (catálogo,
 * validação da carteirinha) e as com login.
 */

use App\Models\User;

test('a tela pública traz o widget do VLibras', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('vw-access-button', false)
        ->assertSee('https://vlibras.gov.br/app/vlibras-plugin.js', false);
});

test('a tela com login também traz o widget do VLibras', function () {
    $this->actingAs(User::factory()->create())->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('vw-access-button', false);
});
