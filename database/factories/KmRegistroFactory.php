<?php

namespace Database\Factories;

use App\Models\KmRegistro;
use App\Models\Motorista;
use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

class KmRegistroFactory extends Factory
{
    protected $model = KmRegistro::class;

    public function definition(): array
    {
        $kmAnterior = fake()->numberBetween(1000, 100000);

        return [
            'veiculo_id' => Veiculo::factory(),
            'motorista_id' => Motorista::factory(),
            'km_anterior' => $kmAnterior,
            'km_atual' => $kmAnterior + fake()->numberBetween(10, 500),
            'registrado_at' => now(),
        ];
    }
}
