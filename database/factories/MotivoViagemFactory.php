<?php

namespace Database\Factories;

use App\Models\MotivoViagem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MotivoViagemFactory extends Factory
{
    protected $model = MotivoViagem::class;

    public function definition(): array
    {
        $nome = 'Motivo '.$this->faker->unique()->words(3, true);

        return [
            'codigo' => Str::slug($nome, '_'),
            'nome' => $nome,
            'tipo_veiculo' => 'administrativo',
            'disponivel_solicitacao' => false,
            'ativo' => true,
        ];
    }
}
