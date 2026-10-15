<?php

/*
 * Testes da INDEXAÇÃO no Google (APP_INDEXAVEL): por padrão, as páginas pedem
 * para não aparecer na busca. Usa o catálogo de cursos, a página pública da
 * plataforma (a raiz "/" só redireciona desde 14/10/2026).
 */

test('por padrão as páginas pedem para não serem indexadas', function () {
    $this->get(route('catalogo.index'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

test('com APP_INDEXAVEL ligado a página pode ser indexada', function () {
    config(['app.indexavel' => true]);

    $this->get(route('catalogo.index'))
        ->assertOk()
        ->assertDontSee('noindex', false);
});
