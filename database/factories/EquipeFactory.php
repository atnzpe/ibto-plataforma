<?php

/*
 * "Fábrica" de equipes para os TESTES. Os dados são inventados (nunca dados
 * reais de pessoas, regra de LGPD do projeto).
 */

namespace Database\Factories;

use App\Enums\StatusEquipe;
use App\Models\Equipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipe>
 */
class EquipeFactory extends Factory
{
    /**
     * Uma equipe em homologação, só com o nome (como as da planilha).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => 'Equipe teste '.fake()->unique()->numberBetween(1000, 9999),
            'status' => StatusEquipe::EmHomologacao,
        ];
    }

    /**
     * Variação: equipe completa (cidade, UF, responsável e contato inventados).
     */
    public function completa(): static
    {
        return $this->state(fn () => [
            'cidade' => 'Recife',
            'uf' => 'PE',
            'responsavel_nome' => 'Responsável Teste',
            'responsavel_telefone' => '81999990000',
            'responsavel_email' => 'responsavel@ibto.test',
        ]);
    }
}
