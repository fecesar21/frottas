<?php

namespace Database\Seeders;

use App\Models\Motorista;
use App\Models\Unidade;
use App\Models\Veiculo;
use Illuminate\Database\Seeder;

class UnidadeSeeder extends Seeder
{
    public function run(): void
    {
        $matriz = Unidade::firstOrCreate(
            ['nome' => 'Matriz'],
            ['tipo' => 'matriz', 'ativo' => true]
        );

        // Vincula todos os motoristas e veículos existentes à Matriz
        $motoristas = Motorista::pluck('id');
        $veiculos = Veiculo::pluck('id');

        $matriz->motoristas()->syncWithoutDetaching($motoristas->toArray());
        $matriz->veiculos()->syncWithoutDetaching($veiculos->toArray());
    }
}
