<?php

/*
 * "Fábrica" de áreas de atuação para os TESTES: cria áreas inventadas no banco
 * de teste, sem precisar escrever cada campo à mão.
 */

namespace Database\Factories;

use App\Models\AreaAtuacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AreaAtuacao>
 */
class AreaAtuacaoFactory extends Factory
{
    /**
     * Valores padrão de uma área inventada.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Nome único inventado (ex.: "Área teste 4821"), para não bater com as 15 reais.
            'nome' => 'Área teste '.fake()->unique()->numberBetween(1000, 9999),
            // Por padrão a área nasce ativa, como no cadastro de verdade.
            'ativa' => true,
        ];
    }

    /**
     * Variação: área desativada. Uso: AreaAtuacao::factory()->desativada()->create()
     */
    public function desativada(): static
    {
        return $this->state(fn () => ['ativa' => false]);
    }
}
