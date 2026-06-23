<?php

namespace Database\Seeders;

use App\Models\Veiculo;
use Illuminate\Database\Seeder;

class VeiculoSeeder extends Seeder
{
    public function run(): void
    {
        $veiculos = [
            ['placa' => 'ABC-1234', 'modelo' => 'Sprinter', 'marca' => 'Mercedes-Benz', 'ano' => 2021, 'cor' => 'Branco', 'combustivel' => 'diesel_s10', 'capacidade_tanque' => 120, 'km_atual' => 45000],
        ];

        foreach ($veiculos as $dados) {
            Veiculo::firstOrCreate(['placa' => $dados['placa']], array_merge($dados, ['status' => 'disponivel']));
        }
    }
}
