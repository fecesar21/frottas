<?php

namespace Database\Factories;

use App\Models\Unidade;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnidadeFactory extends Factory
{
    protected $model = Unidade::class;

    public function definition(): array
    {
        return [
            'nome' => fake()->company(),
            'tipo' => 'filial',
            'ativo' => true,
        ];
    }
}
