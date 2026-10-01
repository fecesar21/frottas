<?php

namespace Database\Factories;

use App\Models\Colaborador;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Colaborador>
 */
class ColaboradorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unidade_id' => Unidade::factory(),
            'ldap_guid' => (string) Str::uuid(),
            'nome' => mb_strtoupper(fake()->name()),
            'samaccountname' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'departamento' => 'ENFERMAGEM',
            'cargo' => 'TÉCNICO',
            'ativo' => true,
            'sincronizado_at' => now(),
        ];
    }
}
