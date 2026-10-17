<?php

/*
 * Testes da POLÍTICA DE PRIVACIDADE (LGPD, #47): a página é pública; o aceite
 * fica registrado com a versão e a data (sem duplicar); revogar grava a data e
 * não apaga; com o aceite obrigatório LIGADO, quem não aceitou vai para a
 * política (e consegue aceitar e sair); desligado (o padrão), nada muda.
 */

use App\Models\Aceite;
use App\Models\User;

test('a política é pública e mostra a versão', function () {
    $this->get(route('privacidade'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('privacidade')->where('versao', config('lgpd.politica_versao'))->where('logado', false));
});

test('aceitar registra a versão e a data, sem duplicar; revogar grava a data', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->post(route('privacidade.aceitar'))->assertRedirect();
    $this->actingAs($usuario)->post(route('privacidade.aceitar'));

    $aceite = Aceite::where('user_id', $usuario->id)->sole();
    expect($aceite->versao)->toBe(config('lgpd.politica_versao'))->and($aceite->revogado_em)->toBeNull();

    $this->actingAs($usuario)->delete(route('privacidade.revogar'))->assertRedirect(route('privacidade'));
    expect($aceite->fresh()->revogado_em)->not->toBeNull()
        ->and(Aceite::daPoliticaAtual($usuario->id))->toBeNull();
    $this->assertDatabaseHas('auditoria', ['acao' => 'POLITICA_REVOGADA']);
});

test('desligado (padrão): ninguém é barrado', function () {
    $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertOk();
});

test('ligado: quem não aceitou vai para a política, aceita e volta para a tela que queria', function () {
    config(['lgpd.exigir_aceite' => true]);
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->get(route('profile.edit'))->assertRedirect(route('privacidade'));
    // A própria política continua abrindo (senão a pessoa ficaria presa).
    $this->actingAs($usuario)->get(route('privacidade'))->assertOk()->assertInertia(fn ($tela) => $tela->where('exigido', true));

    $this->actingAs($usuario)->post(route('privacidade.aceitar'))->assertRedirect(route('profile.edit'));
    $this->actingAs($usuario)->get(route('profile.edit'))->assertOk();
});

test('trocar a versão da política pede o aceite de novo', function () {
    config(['lgpd.exigir_aceite' => true]);
    $usuario = User::factory()->create();
    $this->actingAs($usuario)->post(route('privacidade.aceitar'));

    config(['lgpd.politica_versao' => '9.9']);

    $this->actingAs($usuario)->get(route('profile.edit'))->assertRedirect(route('privacidade'));
});
