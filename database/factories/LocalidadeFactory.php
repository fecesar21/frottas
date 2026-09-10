<?php

namespace Database\Factories;

use App\Models\Localidade;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocalidadeFactory extends Factory
{
    protected $model = Localidade::class;

    public function definition(): array
    {
        return [
            'nome' => $this->faker->company(),
            'endereco' => $this->faker->streetAddress(),
            'latitude' => $this->faker->latitude(-33, 5),
            'longitude' => $this->faker->longitude(-73, -34),
            'telefone' => $this->faker->numerify('(##) ####-####'),
            'email' => $this->faker->safeEmail(),
            'ativo' => true,
        ];
    }
}
