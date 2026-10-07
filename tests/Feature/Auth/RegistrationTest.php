<?php

namespace Tests\Feature\Auth;

use App\Notifications\ConfirmarEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    /**
     * O cadastro termina na hora e o e-mail de confirmação vai para a fila.
     *
     * Por quê: se o e-mail saísse na hora e o servidor de e-mail (Titan)
     * falhasse, o cadastro daria erro 500. Na fila, quem envia é o Cron,
     * com até 3 tentativas, e a pessoa nem percebe a falha.
     */
    public function test_confirmation_email_goes_to_the_queue_on_registration(): void
    {
        // Troca a fila de verdade por uma "falsa" que só anota o que entrou
        // nela, sem executar nada; assim dá para conferir o que foi enfileirado.
        Queue::fake();

        // Faz um cadastro como a pessoa faria pelo formulário.
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // O cadastro terminou normalmente: entrou e foi para o painel.
        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        // Na fila entrou um pedido de envio de notificação, e é a
        // ConfirmarEmail. Se o e-mail tivesse saído na hora, a fila
        // estaria vazia e este teste falharia.
        Queue::assertPushed(
            SendQueuedNotifications::class,
            fn (SendQueuedNotifications $job) => $job->notification instanceof ConfirmarEmail,
        );
    }
}
