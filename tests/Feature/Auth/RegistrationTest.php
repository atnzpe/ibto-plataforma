<?php

/*
 * Testes da tela de CADASTRO PÚBLICO ("Pedir acesso"). Desde a #84 (grupo
 * fechado), o pedido leva a equipe e quem indicou, e a conta nasce "em
 * análise", sem papel; o papel Aluno só vem na aprovação (ver
 * tests/Feature/CadastroComAprovacaoTest.php). Dados INVENTADOS.
 */

namespace Tests\Feature\Auth;

use App\Models\Equipe;
use App\Models\User;
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
        // Cadastro público aberto (com aprovação), o padrão.
        config(['app.cadastro_publico' => 'aprovacao']);
    }

    /**
     * Os dados de um pedido de acesso válido (com uma equipe de teste).
     *
     * @return array<string, mixed>
     */
    private function pedido(): array
    {
        return [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'equipe_id' => Equipe::factory()->create(['nome' => 'Equipe Teste do Cadastro', 'status' => 'ativa'])->id,
            'indicado_por' => 'Mestre Teste',
        ];
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        // A tela abre e já traz a lista de equipes para escolher.
        $response->assertOk()->assertInertia(fn ($tela) => $tela->component('auth/register')->has('equipes'));
    }

    /**
     * O pedido entra, a pessoa fica logada e cai na tela de espera (em análise).
     */
    public function test_new_users_can_request_access()
    {
        $response = $this->post(route('register.store'), $this->pedido());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    /**
     * Sem papel até a aprovação (#84): nem Aluno, nem nenhum outro.
     */
    public function test_new_users_start_without_roles_until_approved(): void
    {
        $this->post(route('register.store'), $this->pedido());

        $usuario = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertSame('em_analise', $usuario->situacao_cadastro);
        $this->assertSame([], $usuario->papeis()->pluck('slug')->all());
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
        // Troca a fila de verdade por uma "falsa" que só anota o que entrou nela.
        Queue::fake();

        $response = $this->post(route('register.store'), $this->pedido());

        // O pedido terminou normalmente.
        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        // Na fila entrou a ConfirmarEmail (se tivesse saído na hora, a fila estaria vazia).
        Queue::assertPushed(
            SendQueuedNotifications::class,
            fn (SendQueuedNotifications $job) => $job->notification instanceof ConfirmarEmail,
        );
    }
}
