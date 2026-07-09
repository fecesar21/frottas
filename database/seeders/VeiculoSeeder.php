<?php

namespace Database\Seeders;

use App\Models\Veiculo;
use Illuminate\Database\Seeder;

class VeiculoSeeder extends Seeder
{
    public function run(): void
    {
        $veiculos = [
            ['placa' => 'CGL-2J92', 'modelo' => 'AMBULANCIA UPA', 'marca' => 'Mercedes-Benz', 'ano' => 2024, 'cor' => 'BRANCO', 'combustivel' => 'diesel_s10', 'capacidade_tanque' => '45', 'km_atual' => 1],
            ['placa' => 'DDA-6G10', 'modelo' => 'AMBULÂNCIA UPA', 'marca' => 'Marcedez Benz', 'ano' => 2024, 'cor' => 'BRANCO', 'combustivel' => 'diesel_s10', 'capacidade_tanque' => '45', 'km_atual' => 1],
            ['placa' => 'MXF-1B89', 'modelo' => 'Strada', 'marca' => 'Fiat', 'ano' => 2021, 'cor' => '', 'combustivel' => 'gasolina', 'capacidade_tanque' => '45', 'km_atual' => 1],
            ['placa' => 'PKQ-3D32', 'modelo' => 'AMBULÂNCIA UPA', 'marca' => 'Mercedes-Benz', 'ano' => 2018, 'cor' => 'BRANCO', 'combustivel' => 'diesel_s10', 'capacidade_tanque' => '45', 'km_atual' => 1],
            ['placa' => 'RIN-9A91', 'modelo' => 'AMBULÂNCIA PAI', 'marca' => 'Mercedes-Benz', 'ano' => 2017, 'cor' => 'BRANCO', 'combustivel' => 'diesel_s10', 'capacidade_tanque' => '45', 'km_atual' => 1],
            ['placa' => 'RSC-8D13', 'modelo' => 'DUSTER', 'marca' => 'Renault', 'ano' => 2022, 'cor' => 'BRANCO', 'combustivel' => 'flex', 'capacidade_tanque' => '45', 'km_atual' => 1],
            ['placa' => 'RT0-9J21', 'modelo' => 'AMBULÂNCIA UPA', 'marca' => 'Mercedes-Benz', 'ano' => 2019, 'cor' => 'BRANCO', 'combustivel' => 'diesel_s10', 'capacidade_tanque' => '45', 'km_atual' => 1],
            ['placa' => 'SDO-5B00', 'modelo' => 'COROLLA', 'marca' => 'Toyota', 'ano' => 2023, 'cor' => 'BRANCO', 'combustivel' => 'flex', 'capacidade_tanque' => '45', 'km_atual' => 1],
            ['placa' => 'TVD-7F43', 'modelo' => 'Fiorino', 'marca' => 'Fiat', 'ano' => 2020, 'cor' => 'BRANCO', 'combustivel' => 'flex', 'capacidade_tanque' => '', 'km_atual' => 1],
        ];

        foreach ($veiculos as $dados) {
            Veiculo::firstOrCreate(['placa' => $dados['placa']], array_merge($dados, ['status' => 'disponivel']));
        }
    }
}
