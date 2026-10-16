<?php

/*
 * Testes da PÁGINA AJUDA (#68, parte 2): cada pessoa vê o manual do seu
 * papel; Administrador e Tecnologia veem todos; sem login, vai para entrar.
 * Dados INVENTADOS.
 */

use App\Http\Controllers\AjudaController;
use App\Models\Papel;
use App\Models\User;

/**
 * Uma conta com os papéis pedidos.
 *
 * @param  list<string>  $papeis  Ex.: ['instrutor']
 */
function contaComPapeis(array $papeis): User
{
    $usuario = User::factory()->create();
    $usuario->papeis()->attach(Papel::whereIn('slug', $papeis)->pluck('id'));

    return $usuario;
}

test('aluno vê só o manual do aluno', function () {
    $this->actingAs(contaComPapeis(['aluno']))->get(route('ajuda'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('ajuda/index')->where('manuais', ['aluno']));
});

test('instrutor e admin de equipe veem também o manual do seu papel', function () {
    $this->actingAs(contaComPapeis(['instrutor', 'admin_equipe']))->get(route('ajuda'))
        ->assertInertia(fn ($tela) => $tela->where('manuais', ['aluno', 'instrutor', 'admin-equipe']));
});

test('Administrador e Tecnologia veem todos os manuais (transparência)', function (string $papel) {
    $this->actingAs(contaComPapeis([$papel]))->get(route('ajuda'))
        ->assertInertia(fn ($tela) => $tela->where('manuais', ['aluno', 'instrutor', 'admin-equipe', 'comando-geral', 'tecnologia']));
})->with(['administrador', 'tecnologia']);

test('a regra dos manuais por papel', function () {
    expect(AjudaController::manuaisDosPapeis([]))->toBe(['aluno'])
        ->and(AjudaController::manuaisDosPapeis(['filiado']))->toBe(['aluno'])
        ->and(AjudaController::manuaisDosPapeis(['instrutor']))->toBe(['aluno', 'instrutor']);
});

test('sem login, a Ajuda leva para a tela de entrar', function () {
    $this->get(route('ajuda'))->assertRedirect(route('login'));
});
