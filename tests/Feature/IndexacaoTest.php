<?php

test('por padrão as páginas pedem para não serem indexadas', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

test('com APP_INDEXAVEL ligado a página pode ser indexada', function () {
    config(['app.indexavel' => true]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('noindex', false);
});
