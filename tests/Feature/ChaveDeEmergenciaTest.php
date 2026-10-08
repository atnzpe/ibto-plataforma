<?php

/*
 * Testes da chave de emergência (#37, #36): e-mails da lista ADMIN_EMAILS,
 * já confirmados, passam em qualquer permissão (aqui, "cadastros.areas").
 */

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    // Lista de administradores do teste (como se viesse do .env, já em minúsculas).
    config(['app.admin_emails' => ['admin@ibto.test']]);
});

test('libera o e-mail da lista que já foi confirmado', function () {
    $admin = User::factory()->create(['email' => 'admin@ibto.test']);

    expect(Gate::forUser($admin)->allows('cadastros.areas'))->toBeTrue();
});

test('barra quem não está na lista', function () {
    $aluno = User::factory()->create(['email' => 'aluno@ibto.test']);

    expect(Gate::forUser($aluno)->allows('cadastros.areas'))->toBeFalse();
});

test('barra o e-mail da lista que ainda não foi confirmado', function () {
    // Alguém criou conta com o e-mail do administrador, mas não tem acesso à caixa de entrada.
    $impostor = User::factory()->unverified()->create(['email' => 'admin@ibto.test']);

    expect(Gate::forUser($impostor)->allows('cadastros.areas'))->toBeFalse();
});

test('não diferencia maiúsculas de minúsculas no e-mail', function () {
    $admin = User::factory()->create(['email' => 'Admin@IBTO.test']);

    expect(Gate::forUser($admin)->allows('cadastros.areas'))->toBeTrue();
});

test('lista vazia: ninguém é administrador', function () {
    config(['app.admin_emails' => []]);
    $usuario = User::factory()->create(['email' => 'admin@ibto.test']);

    expect(Gate::forUser($usuario)->allows('cadastros.areas'))->toBeFalse();
});

test('lista de administradores inválida (cache antigo) não quebra: ninguém é chave e fica anotado no log', function () {
    // Simula o erro de 07/10/2026: configuração em cache sem a lista (null).
    config(['app.admin_emails' => null]);
    Log::spy();
    $usuario = User::factory()->create(['email' => 'admin@ibto.test']);

    expect(Gate::forUser($usuario)->allows('cadastros.areas'))->toBeFalse();
    Log::shouldHaveReceived('error')->with('ADMIN_EMAILS_CONFIG_INVALIDA', ['tipo' => 'null']);
});
