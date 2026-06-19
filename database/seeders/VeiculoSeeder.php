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
            ['placa' => 'DEF-5678', 'modelo' => 'Transit',  'marca' => 'Ford',           'ano' => 2020, 'cor' => 'Prata',  'combustivel' => 'diesel_s10', 'capacidade_tanque' => 100, 'km_atual' => 78000],
            ['placa' => 'GHI-9012', 'modelo' => 'Daily',    'marca' => 'Iveco',          'ano' => 2022, 'cor' => 'Branco', 'combustivel' => 'diesel_s500','capacidade_tanque' => 130, 'km_atual' => 22000],
            ['placa' => 'JKL-3456', 'modelo' => 'Ducato',   'marca' => 'Fiat',           'ano' => 2019, 'cor' => 'Cinza',  'combustivel' => 'diesel_s10', 'capacidade_tanque' => 90,  'km_atual' => 110000],
            ['placa' => 'MNO-7890', 'modelo' => 'Accelo',   'marca' => 'Mercedes-Benz', 'ano' => 2023, 'cor' => 'Branco', 'combustivel' => 'diesel_s500','capacidade_tanque' => 200, 'km_atual' => 5000],
        ];

        foreach ($veiculos as $dados) {
            Veiculo::firstOrCreate(['placa' => $dados['placa']], array_merge($dados, ['status' => 'disponivel']));
        }
    }
}
