<?php

namespace Database\Factories;

use App\Models\Checkin;
use App\Models\Motorista;
use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

class CheckinFactory extends Factory
{
    protected $model = Checkin::class;

    public function definition(): array
    {
        $kmSaida = fake()->numberBetween(1000, 100000);

        return [
            'motorista_id'              => Motorista::factory(),
            'veiculo_id'                => Veiculo::factory(),
            'turno'                     => fake()->randomElement(['dia', 'noite']),
            'km_saida'                  => $kmSaida,
            'nivel_combustivel_saida'   => fake()->numberBetween(20, 100),
            'checkin_at'                => now()->subHours(fake()->numberBetween(1, 8)),
            'status'                    => 'ativo',
        ];
    }

    public function encerrado(): static
    {
        return $this->state(function (array $attributes) {
            $kmRetorno = $attributes['km_saida'] + fake()->numberBetween(10, 500);
            return [
                'km_retorno'                 => $kmRetorno,
                'nivel_combustivel_retorno'  => fake()->numberBetween(5, 80),
                'checkout_at'                => now(),
                'status'                     => 'encerrado',
            ];
        });
    }
}
