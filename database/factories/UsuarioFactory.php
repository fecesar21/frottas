<?php

namespace Database\Factories;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    public function definition(): array
    {
        return [
            'nome'       => fake()->name(),
            'email'      => fake()->unique()->safeEmail(),
            'senha_hash' => Hash::make('password'),
            'perfil'     => fake()->randomElement(['gestor', 'operador']),
            'ativo'      => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(['perfil' => 'admin']);
    }
}
