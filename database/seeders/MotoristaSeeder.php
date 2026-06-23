<?php

namespace Database\Seeders;

use App\Models\Motorista;
use Illuminate\Database\Seeder;

class MotoristaSeeder extends Seeder
{
    public function run(): void
    {
        $motoristas = [
            ['nome' => 'João Silva',    'cpf' => '111.111.111-11', 'cnh_numero' => '11111111111', 'cnh_categoria' => 'D', 'cnh_validade' => '2027-06-01', 'turno_padrao' => 'dia'],
        ];

        foreach ($motoristas as $dados) {
            Motorista::firstOrCreate(['cpf' => $dados['cpf']], array_merge($dados, [
                'status' => 'ativo',
                'telefone' => '(11) 9' . rand(1000, 9999) . '-' . rand(1000, 9999),
            ]));
        }
    }
}
