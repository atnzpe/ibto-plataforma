<?php

/*
 * Testes das DUAS ETAPAS OBRIGATÓRIAS DA EQUIPE (07/11/2026): quem tem acesso
 * sensível (ex.: administrador) e não ligou a segunda etapa é levado para a tela
 * de Segurança; quem ligou, o aluno e o instrutor comum seguem livres; a regra
 * pode ser desligada no .env; as telas para ligar e para sair nunca são barradas.
 * Dados INVENTADOS.
 */

use App\Models\Papel;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Liga a regra só nestes testes (no phpunit.xml ela vem desligada).
    config(['seguranca.exigir_2fa_equipe' => true]);

    // Administrador inventado: tem financeiro, filiados e permissões (acesso sensível).
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
});

/**
 * Liga a verificação em duas etapas (já confirmada) na conta, como se a pessoa
 * tivesse lido o QR code e digitado o código.
 *
 * @param  User  $usuario  A conta inventada. Ex.: o administrador do teste.
 * @return User A mesma conta, já com a segunda etapa ligada.
 */
function comDuasEtapas(User $usuario): User
{
    $usuario->forceFill([
        'two_factor_secret' => encrypt('SEGREDO-DE-TESTE'),
        'two_factor_recovery_codes' => encrypt(json_encode(['codigo-1'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $usuario;
}

/**
 * Confere que a pessoa NÃO foi levada para a tela de Segurança. A resposta pode
 * ser a tela (200) ou um desvio normal (ex.: o administrador vai do painel para
 * o Comando Geral), desde que não seja para a Segurança.
 *
 * @param  TestResponse  $resposta  A resposta do pedido. Ex.: $this->get(route('dashboard')).
 */
function naoBarrado(TestResponse $resposta): void
{
    expect($resposta->status())->toBeIn([200, 302])
        ->and($resposta->headers->get('Location'))->not->toBe(route('security.edit'));
}

test('administrador sem a segunda etapa é levado para a Segurança', function () {
    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertRedirect(route('security.edit'));
});

test('a tela pedida fica guardada para voltar depois de ligar', function () {
    $this->actingAs($this->admin)->get(route('dashboard'));

    expect(session('url.intended'))->toBe(route('dashboard'));
});

test('administrador com a segunda etapa ligada usa normalmente', function () {
    naoBarrado($this->actingAs(comDuasEtapas($this->admin))->get(route('dashboard')));
});

test('aluno sem papel nenhum não é barrado', function () {
    naoBarrado($this->actingAs(User::factory()->create())->get(route('dashboard')));
});

test('instrutor comum não é barrado', function () {
    $instrutor = User::factory()->create();
    $instrutor->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));

    naoBarrado($this->actingAs($instrutor)->get(route('dashboard')));
});

test('com a regra desligada no .env, ninguém é barrado', function () {
    config(['seguranca.exigir_2fa_equipe' => false]);

    naoBarrado($this->actingAs($this->admin)->get(route('dashboard')));
});

test('a tela de Segurança abre e mostra o aviso para quem precisa ligar', function () {
    $this->actingAs($this->admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $tela) => $tela
            ->component('settings/security')
            ->where('exigeDuasEtapas', true));
});

test('depois de ligar, o aviso some da tela de Segurança', function () {
    $this->actingAs(comDuasEtapas($this->admin))
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $tela) => $tela->where('exigeDuasEtapas', false));
});

test('quem precisa ligar ainda consegue sair da conta', function () {
    $this->actingAs($this->admin)->post(route('logout'))
        ->assertRedirect();

    $this->assertGuest();
});
