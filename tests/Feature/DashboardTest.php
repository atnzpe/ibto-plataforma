<?php

namespace Tests\Feature;

use App\Models\Papel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    /**
     * Quem não é da equipe do IBTO começa em "Meus cursos" (desenho aprovado em 10/10/2026).
     */
    public function test_aluno_vai_para_meus_cursos()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('meus-cursos'));
        $this->get(route('meus-cursos'))->assertOk();
    }

    /**
     * Equipe do IBTO (com "comando_geral.ver") não tem "Dashboard" separado:
     * o início dela é o Comando Geral (Gleyson, 10/10/2026).
     */
    public function test_equipe_do_ibto_vai_direto_para_o_comando_geral(): void
    {
        $admin = User::factory()->create();
        $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('comando-geral'));
    }
}
