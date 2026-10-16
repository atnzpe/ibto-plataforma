<?php

/*
 * Testes do TOUR GUIADO (#68): o interruptor da conta e a lista de telas já
 * vistas. Só a própria conta muda; entrada estranha é recusada. Dados INVENTADOS.
 */

use App\Models\User;

test('conta nova começa com o tour ligado e nenhuma tela vista', function () {
    $usuario = User::factory()->create()->refresh();

    expect($usuario->tour_ativo)->toBeTrue()
        ->and($usuario->tours_vistos)->toBeNull();
});

test('as telas recebem o interruptor e as telas vistas da pessoa logada', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->get(route('profile.edit'))
        ->assertInertia(fn ($tela) => $tela->where('tour.ativo', true)->where('tour.vistos', []));
});

test('desligar e religar: religar zera as telas vistas', function () {
    $usuario = User::factory()->create();
    $usuario->forceFill(['tours_vistos' => ['comando-geral']])->save();

    $this->actingAs($usuario)->put(route('tour.atualizar'), ['ativo' => false])->assertRedirect();
    expect($usuario->refresh()->tour_ativo)->toBeFalse()
        ->and($usuario->tours_vistos)->toBe(['comando-geral']);

    $this->put(route('tour.atualizar'), ['ativo' => true])->assertSessionHas('sucesso', 'Tour guiado ligado.');
    expect($usuario->refresh()->tour_ativo)->toBeTrue()
        ->and($usuario->tours_vistos)->toBe([]);
});

test('marcar tela como vista não repete a tela', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->post(route('tour.visto'), ['tela' => 'cursos/matriculas']);
    $this->post(route('tour.visto'), ['tela' => 'cursos/matriculas']);
    $this->post(route('tour.visto'), ['tela' => 'meus-cursos']);

    expect($usuario->refresh()->tours_vistos)->toBe(['cursos/matriculas', 'meus-cursos']);
});

test('entrada estranha é recusada', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->post(route('tour.visto'), ['tela' => '<script>'])->assertSessionHasErrors('tela');
    $this->post(route('tour.visto'), ['tela' => str_repeat('a', 61)])->assertSessionHasErrors('tela');
    $this->put(route('tour.atualizar'), ['ativo' => 'talvez'])->assertSessionHasErrors('ativo');
});

test('sem login, não muda nada (vai para a tela de entrar)', function () {
    $this->put(route('tour.atualizar'), ['ativo' => false])->assertRedirect(route('login'));
    $this->post(route('tour.visto'), ['tela' => 'meus-cursos'])->assertRedirect(route('login'));
});
