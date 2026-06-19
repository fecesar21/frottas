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
            ['nome' => 'Maria Souza',   'cpf' => '222.222.222-22', 'cnh_numero' => '22222222222', 'cnh_categoria' => 'B', 'cnh_validade' => '2026-12-15', 'turno_padrao' => 'noite'],
            ['nome' => 'Carlos Lima',   'cpf' => '333.333.333-33', 'cnh_numero' => '33333333333', 'cnh_categoria' => 'E', 'cnh_validade' => '2028-03-20', 'turno_padrao' => 'dia'],
            ['nome' => 'Ana Pereira',   'cpf' => '444.444.444-44', 'cnh_numero' => '44444444444', 'cnh_categoria' => 'C', 'cnh_validade' => '2026-08-10', 'turno_padrao' => 'noite'],
            ['nome' => 'Pedro Oliveira','cpf' => '555.555.555-55', 'cnh_numero' => '55555555555', 'cnh_categoria' => 'D', 'cnh_validade' => '2025-09-30', 'turno_padrao' => 'dia'],
        ];

        foreach ($motoristas as $dados) {
            Motorista::firstOrCreate(['cpf' => $dados['cpf']], array_merge($dados, [
                'status' => 'ativo',
                'telefone' => '(11) 9' . rand(1000, 9999) . '-' . rand(1000, 9999),
            ]));
        }
    }
}
