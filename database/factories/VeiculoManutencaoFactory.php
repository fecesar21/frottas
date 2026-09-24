<?php

namespace Database\Factories;

use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

class VeiculoManutencaoFactory extends Factory
{
    public function definition(): array
    {
        $inicio = now()->subDays(fake()->numberBetween(1, 20));

        return [
            'veiculo_id' => Veiculo::factory(),
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addHours(fake()->numberBetween(2, 72)),
            'motivo' => fake()->randomElement(['Troca de óleo', 'Pneus', 'Funilaria', null]),
        ];
    }
}
