<?php

namespace Database\Factories;

use App\Models\Motorista;
use Illuminate\Database\Eloquent\Factories\Factory;

class MotoristaFactory extends Factory
{
    protected $model = Motorista::class;

    public function definition(): array
    {
        return [
            'nome'          => fake()->name(),
            'cpf'           => fake()->unique()->numerify('###.###.###-##'),
            'telefone'      => fake()->phoneNumber(),
            'email'         => fake()->unique()->safeEmail(),
            'cnh_numero'    => fake()->unique()->numerify('###########'),
            'cnh_categoria' => fake()->randomElement(['A', 'B', 'C', 'D', 'E', 'AB', 'AC']),
            'cnh_validade'  => fake()->dateTimeBetween('+1 month', '+5 years')->format('Y-m-d'),
            'turno_padrao'  => fake()->randomElement(['dia', 'noite']),
            'status'        => 'ativo',
        ];
    }

    public function cnhVencendo(): static
    {
        return $this->state([
            'cnh_validade' => fake()->dateTimeBetween('now', '+29 days')->format('Y-m-d'),
        ]);
    }
}
