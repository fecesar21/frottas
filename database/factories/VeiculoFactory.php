<?php

namespace Database\Factories;

use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

class VeiculoFactory extends Factory
{
    protected $model = Veiculo::class;

    public function definition(): array
    {
        $marcas  = ['Volkswagen', 'Ford', 'Chevrolet', 'Fiat', 'Mercedes-Benz', 'Iveco', 'Scania'];
        $modelos = ['Sprinter', 'Transit', 'Ducato', 'Daily', 'Accelo', 'Gol', 'Kombi'];

        return [
            'placa'              => strtoupper(fake()->unique()->bothify('???-####')),
            'modelo'             => fake()->randomElement($modelos),
            'marca'              => fake()->randomElement($marcas),
            'ano'                => fake()->numberBetween(2015, 2024),
            'cor'                => fake()->colorName(),
            'chassi'             => strtoupper(fake()->unique()->bothify('?????????????????')),
            'renavam'            => fake()->unique()->numerify('###########'),
            'combustivel'        => fake()->randomElement(['diesel_s10', 'diesel_s500', 'gasolina', 'flex']),
            'capacidade_tanque'  => fake()->numberBetween(50, 200),
            'km_atual'           => fake()->numberBetween(0, 200000),
            'status'             => 'disponivel',
        ];
    }

    public function emUso(): static
    {
        return $this->state(['status' => 'em_uso']);
    }
}
