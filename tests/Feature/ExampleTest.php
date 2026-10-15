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
     * Visitante (sem entrar): vai para o catálogo de cursos.
     */
    public function test_visitante_vai_para_o_catalogo()
    {
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
