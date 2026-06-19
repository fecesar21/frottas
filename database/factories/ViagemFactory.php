<?php

namespace Database\Factories;

use App\Models\Motorista;
use App\Models\Veiculo;
use App\Models\Viagem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ViagemFactory extends Factory
{
    protected $model = Viagem::class;

    public function definition(): array
    {
        $cidades = ['São Paulo', 'Campinas', 'Santos', 'Sorocaba', 'Ribeirão Preto', 'Bauru', 'Piracicaba'];
        $kmSaida = fake()->numberBetween(1000, 100000);

        return [
            'veiculo_id'   => Veiculo::factory(),
            'motorista_id' => Motorista::factory(),
            'origem'       => fake()->randomElement($cidades),
            'destino'      => fake()->randomElement($cidades),
            'km_saida'     => $kmSaida,
            'saida_at'     => now()->subHours(fake()->numberBetween(1, 12)),
            'status'       => 'em_andamento',
        ];
    }

    public function concluida(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'km_chegada' => $attributes['km_saida'] + fake()->numberBetween(50, 800),
                'chegada_at' => now(),
                'status'     => 'concluida',
            ];
        });
    }
}
