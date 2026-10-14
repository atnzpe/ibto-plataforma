<?php

/*
 * Testes das AÇÕES NA CONTA na tela de Usuários (#36; pedido do Gleyson em
 * 10/10/2026): desativar/reativar (soft delete: nada é apagado), o porteiro
 * que desloga a conta desativada, o link de nova senha (ninguém vê senha) e o
 * convite (só para quem ainda não criou a senha). Auditoria só com números.
 * Dados INVENTADOS.
 */

use App\Models\Auditoria;
use App\Models\Papel;
use App\Models\User;
use App\Notifications\ConviteFiliado;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['app.admin_emails' => []]);
    Notification::fake();

    // Administradora: tem usuarios.gerenciar, mas não permissoes.gerenciar.
    $this->administradora = User::factory()->create();
    $this->administradora->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->pessoa = User::factory()->create(['name' => 'Pessoa Teste']);
});

test('desativar: a conta fica no banco, com a data, e vai para a auditoria', function () {
    $this->actingAs($this->administradora)
        ->post(route('admin.usuarios.desativar', $this->pessoa))
        ->assertRedirect(route('admin.usuarios.index'));

    // Nada apagado: a conta continua, só com a data de desativação.
    expect($this->pessoa->fresh()->desativada_em)->not->toBeNull();
    expect(Auditoria::latest('id')->first()->acao)->toBe('CONTA_DESATIVADA');
});

test('conta desativada é deslogada no próximo clique e não entra de novo', function () {
    $this->pessoa->forceFill(['desativada_em' => now()])->save();

    // Estava logada: o porteiro desloga e manda para a tela de entrar com o código.
    $this->actingAs($this->pessoa)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Esta conta está desativada. Fale com a administração do IBTO. (código CONTA_DESATIVADA)']);
    $this->assertGuest();

    // Tentando entrar com a senha certa: não passa da porta.
    $this->post(route('login.store'), ['email' => $this->pessoa->email, 'password' => 'password']);
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('reativar: volta a entrar', function () {
    $this->pessoa->forceFill(['desativada_em' => now()])->save();

    $this->actingAs($this->administradora)->post(route('admin.usuarios.reativar', $this->pessoa));

    expect($this->pessoa->fresh()->desativada_em)->toBeNull();
    expect(Auditoria::latest('id')->first()->acao)->toBe('CONTA_REATIVADA');
});

test('ninguém desativa a própria conta; Administrador não desativa a Tecnologia', function () {
    $this->actingAs($this->administradora)
        ->post(route('admin.usuarios.desativar', $this->administradora))
        ->assertSessionHasErrors('conta');

    $tecnologia = User::factory()->create();
    $tecnologia->papeis()->attach(Papel::where('slug', 'tecnologia')->value('id'));
    $this->actingAs($this->administradora)
        ->post(route('admin.usuarios.desativar', $tecnologia))
        ->assertSessionHasErrors('conta');

    expect($this->administradora->fresh()->desativada_em)->toBeNull();
    expect($tecnologia->fresh()->desativada_em)->toBeNull();
});

test('nova senha: manda o link para a pessoa (a senha não muda) e registra', function () {
    $senhaAntes = $this->pessoa->password;

    $this->actingAs($this->administradora)
        ->post(route('admin.usuarios.nova-senha', $this->pessoa))
        ->assertRedirect(route('admin.usuarios.index'));

    Notification::assertSentTo($this->pessoa, ResetPassword::class);
    // Ninguém trocou a senha: só a própria pessoa troca, pelo link.
    expect($this->pessoa->fresh()->password)->toBe($senhaAntes);
    expect(Auditoria::latest('id')->first()->acao)->toBe('SENHA_LINK_ENVIADO');
});

test('nova senha para conta desativada é recusada', function () {
    $this->pessoa->forceFill(['desativada_em' => now()])->save();

    $this->actingAs($this->administradora)
        ->post(route('admin.usuarios.nova-senha', $this->pessoa))
        ->assertSessionHasErrors('conta');

    Notification::assertNothingSent();
});

test('convite: só para quem ainda não criou a senha', function () {
    // Já criou a senha (e-mail confirmado, como sai da fábrica de testes): recusado.
    $this->actingAs($this->administradora)
        ->post(route('admin.usuarios.convite', $this->pessoa))
        ->assertSessionHasErrors('conta');

    // Ainda não criou: o convite sai.
    $novata = User::factory()->unverified()->create();
    $this->actingAs($this->administradora)
        ->post(route('admin.usuarios.convite', $novata))
        ->assertRedirect(route('admin.usuarios.index'));

    Notification::assertSentTo($novata, ConviteFiliado::class);
    Notification::assertNotSentTo($this->pessoa, ConviteFiliado::class);
});

test('a lista manda o estado de cada conta para os botões', function () {
    $this->pessoa->forceFill(['desativada_em' => now()])->save();

    $this->actingAs($this->administradora)
        ->get(route('admin.usuarios.index', ['busca' => 'Pessoa Teste']))
        ->assertInertia(fn ($tela) => $tela
            ->where('usuarios.data.0.ativa', false)
            ->where('usuarios.data.0.senha_criada', true)
            ->where('usuarios.data.0.eu', false));
});

test('quem não tem usuarios.gerenciar não usa as ações (403)', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('admin.usuarios.desativar', $this->pessoa))
        ->assertForbidden();
});
