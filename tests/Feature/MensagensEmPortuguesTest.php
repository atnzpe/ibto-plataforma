<?php

/*
 * Testes das MENSAGENS DO SERVIDOR EM PORTUGUÊS (lang/pt_BR, 14/10/2026):
 * senha errada no login, campo obrigatório e e-mail de "Esqueci minha senha".
 * Se alguém apagar a pasta lang ou trocar o idioma, estes testes avisam.
 */

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('senha errada no login: a mensagem sai em português', function () {
    $usuario = User::factory()->create();

    $this->post(route('login.store'), ['email' => $usuario->email, 'password' => 'senha-errada'])
        ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
});

test('campo obrigatório: mensagem e nome do campo em português', function () {
    // Cadastro sem nada preenchido, com o "Pedir acesso" aberto (o padrão agora é fechado).
    config(['app.cadastro_publico' => 'aprovacao']);
    $this->post(route('register.store'), [])
        ->assertSessionHasErrors([
            'name' => 'O campo nome é obrigatório.',
            'email' => 'O campo e-mail é obrigatório.',
        ]);
});

test('e-mail de "Esqueci minha senha": assunto e botão em português', function () {
    Notification::fake();
    $usuario = User::factory()->create();

    $this->post(route('password.email'), ['email' => $usuario->email]);

    Notification::assertSentTo($usuario, ResetPassword::class, function (ResetPassword $aviso) use ($usuario) {
        // Monta o e-mail como ele seria enviado e confere os textos.
        $email = $aviso->toMail($usuario);

        // Assunto = a frase "Reset your password" do Laravel; botão = "Reset Password".
        return $email->subject === 'Crie uma senha nova' && $email->actionText === 'Criar uma senha nova';
    });
});
