<?php

namespace Database\Factories;

use App\Models\Motorista;
use App\Models\PassagemPlantao;
use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

class PassagemPlantaoFactory extends Factory
{
    protected $model = PassagemPlantao::class;

    public function definition(): array
    {
        return [
            'veiculo_id' => Veiculo::factory(),
            'motorista_saindo_id' => Motorista::factory(),
            'motorista_entrando_id' => Motorista::factory(),
            'turno_saindo' => 'dia',
            'turno_entrando' => 'noite',
            'km_momento' => fake()->numberBetween(1, 100000),
            'nivel_combustivel' => fake()->numberBetween(0, 100),
        ];
    }
}
