<?php

namespace Database\Factories;

use App\Models\Abastecimento;
use App\Models\Motorista;
use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbastecimentoFactory extends Factory
{
    protected $model = Abastecimento::class;

    public function definition(): array
    {
        return [
            'veiculo_id' => Veiculo::factory(),
            'motorista_id' => Motorista::factory(),
            'combustivel' => fake()->randomElement(['Diesel_s10', 'Diesel_s500', 'Gasolina', 'Etanol']),
            'litros' => fake()->randomFloat(3, 20, 80),
            'valor_litro' => fake()->randomFloat(3, 4.5, 6.5),
            'km_momento' => fake()->numberBetween(1000, 100000),
            'abastecido_at' => now()->subDays(fake()->numberBetween(0, 30)),
        ];
    }
}
