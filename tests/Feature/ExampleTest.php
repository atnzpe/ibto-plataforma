<?php

/*
 * Testes da PÁGINA INICIAL da plataforma (o endereço "/"). Desde 14/10/2026
 * ela não mostra mais a tela "Em construção": só leva cada pessoa ao lugar
 * certo.
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Visitante (sem entrar): vai para a home do site do IBTO (17/10/2026).
     */
    public function test_visitante_vai_para_a_home_do_site()
    {
        config(['app.site_url' => 'https://homolog.ib-to.org']);
        $this->get(route('home'))->assertRedirect('https://homolog.ib-to.org');
    }

    /**
     * Sem o endereço do site configurado (ex.: computador local): o catálogo, como antes.
     */
    public function test_sem_endereco_do_site_vai_para_o_catalogo()
    {
        config(['app.site_url' => '']);
        $this->get(route('home'))->assertRedirect(route('catalogo.index'));
    }

    /**
     * Quem entrou: vai para a página inicial dele (o /dashboard escolhe entre
     * o Comando Geral e "Meus cursos").
     */
    public function test_logado_vai_para_a_pagina_inicial_dele()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }
}
