<?php

/*
 * "Fábrica" de níveis para os TESTES. Atenção: os 13 níveis reais já vêm da
 * migration, então este padrão (Instrutor V) JÁ EXISTE no banco de teste.
 * Para criar um nível com a fábrica, apague antes o real ou passe outra
 * combinação; para os testes do dia a dia, use os 13 que já existem.
 */

namespace Database\Factories;

use App\Enums\CategoriaNivel;
use App\Models\Nivel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Nivel>
 */
class NivelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'categoria' => CategoriaNivel::Instrutor,
            'grau' => 5,
            'ativo' => true,
        ];
    }
}
