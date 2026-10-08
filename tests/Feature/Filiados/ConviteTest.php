<?php

/*
 * Testes do CONVITE do filiado (#37, bloco F3): o e-mail sai ao cadastrar, o
 * link assinado abre a tela, a senha é criada e a pessoa entra; link
 * alterado é recusado; link usado não vale de novo. Dados INVENTADOS.
 */

use App\Actions\Filiados\CriarFiliado;
use App\Models\Filiado;
use App\Models\Papel;
use App\Models\User;
use App\Notifications\ConviteFiliado;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Cria um filiado inventado (e a conta dele) e devolve a conta.
 */
function criarFiliadoDeTeste(): User
{
    return app(CriarFiliado::class)->executar([
        'nome' => 'Convidado Teste',
        'email' => 'convidado@ibto.test',
        'cpf' => '529.982.247-25',
        'nascimento' => '1990-05-20',
        'municipio' => 'Recife',
        'uf' => 'PE',
    ])->usuario;
}

/**
 * Link assinado do convite, igual ao do e-mail.
 */
function linkDoConvite(User $usuario): string
{
    return URL::temporarySignedRoute('convite.mostrar', now()->addDays(7), ['usuario' => $usuario->id]);
}

test('cadastrar um filiado manda o convite por e-mail (pela fila)', function () {
    Notification::fake();

    $usuario = criarFiliadoDeTeste();

    Notification::assertSentTo($usuario, ConviteFiliado::class);
});

test('o link assinado abre a tela de criar senha', function () {
    Notification::fake();
    $usuario = criarFiliadoDeTeste();

    $this->get(linkDoConvite($usuario))
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('auth/convite')
            ->where('nome', 'Convidado Teste'));
});

test('criar a senha confirma o e-mail e já entra logado', function () {
    Notification::fake();
    $usuario = criarFiliadoDeTeste();

    $this->post(linkDoConvite($usuario), [
        'password' => 'Senha-Forte-2026!',
        'password_confirmation' => 'Senha-Forte-2026!',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($usuario);
    expect($usuario->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('link alterado (sem a assinatura certa) é recusado', function () {
    Notification::fake();
    $usuario = criarFiliadoDeTeste();

    // O ataque de verdade: trocar o número no endereço pelo de OUTRA pessoa que
    // existe, para criar a senha dela. A assinatura não bate e o pedido é barrado.
    $outra = User::factory()->unverified()->create();
    $alterado = str_replace("/convite/{$usuario->id}", "/convite/{$outra->id}", linkDoConvite($usuario));

    $this->get($alterado)->assertForbidden();
});

test('link vencido (mais de 7 dias) é recusado', function () {
    Notification::fake();
    $usuario = criarFiliadoDeTeste();
    $link = linkDoConvite($usuario);

    // "Viaja no tempo" 8 dias para frente.
    $this->travel(8)->days();

    $this->get($link)->assertForbidden();
});

test('convite já usado não vale de novo', function () {
    Notification::fake();
    $usuario = criarFiliadoDeTeste();
    $link = linkDoConvite($usuario);
    $this->post($link, ['password' => 'Senha-Forte-2026!', 'password_confirmation' => 'Senha-Forte-2026!']);
    auth()->logout();

    $this->get($link)->assertRedirect(route('login'));
});

test('administrador reenvia o convite só para quem ainda não ativou a conta', function () {
    Notification::fake();
    config(['app.admin_emails' => []]);
    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $usuario = criarFiliadoDeTeste();
    $filiado = Filiado::where('user_id', $usuario->id)->firstOrFail();

    $this->actingAs($admin)
        ->post(route('cadastros.filiados.convite', $filiado))
        ->assertRedirect(route('cadastros.filiados.edit', $filiado));

    // Um no cadastro + um no reenvio.
    Notification::assertSentToTimes($usuario, ConviteFiliado::class, 2);
});
