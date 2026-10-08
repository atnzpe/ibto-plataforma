<?php

/*
 * Testes da trava provisória de administrador (#37, até a #36): o Gate
 * "administrar" só libera e-mails da lista ADMIN_EMAILS que já foram confirmados.
 */

use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    // Lista de administradores do teste (como se viesse do .env, já em minúsculas).
    config(['app.admin_emails' => ['admin@ibto.test']]);
});

test('libera o e-mail da lista que já foi confirmado', function () {
    $admin = User::factory()->create(['email' => 'admin@ibto.test']);

    expect(Gate::forUser($admin)->allows('administrar'))->toBeTrue();
});

test('barra quem não está na lista', function () {
    $aluno = User::factory()->create(['email' => 'aluno@ibto.test']);

    expect(Gate::forUser($aluno)->allows('administrar'))->toBeFalse();
});

test('barra o e-mail da lista que ainda não foi confirmado', function () {
    // Alguém criou conta com o e-mail do administrador, mas não tem acesso à caixa de entrada.
    $impostor = User::factory()->unverified()->create(['email' => 'admin@ibto.test']);

    expect(Gate::forUser($impostor)->allows('administrar'))->toBeFalse();
});

test('não diferencia maiúsculas de minúsculas no e-mail', function () {
    $admin = User::factory()->create(['email' => 'Admin@IBTO.test']);

    expect(Gate::forUser($admin)->allows('administrar'))->toBeTrue();
});

test('lista vazia: ninguém é administrador', function () {
    config(['app.admin_emails' => []]);
    $usuario = User::factory()->create(['email' => 'admin@ibto.test']);

    expect(Gate::forUser($usuario)->allows('administrar'))->toBeFalse();
});
