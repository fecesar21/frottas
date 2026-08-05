<?php

namespace Database\Factories;

use App\Models\Solicitacao;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

class SolicitacaoFactory extends Factory
{
    protected $model = Solicitacao::class;

    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory(),
            'motivo' => 'tfd',
            'status' => 'aberto',
        ];
    }
}
