<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        Usuario::firstOrCreate(
            ['cpf' => '000.000.000-01'],
            [
                'nome'       => 'Fernando César Cardoso',
                'email'      => 'admin@admin.com',
                'senha_hash' => Hash::make('admin@2026'),
                'perfil'     => 'admin',
                'ativo'      => true,
            ]
        );

        Usuario::firstOrCreate(
            ['cpf' => '000.000.000-02'],
            [
                'nome'       => 'Amadeu Lima',
                'email'      => 'amadeu.lima@isac.org.br',
                'senha_hash' => Hash::make('gestor123'),
                'perfil'     => 'gestor',
                'ativo'      => true,
            ]
        );
    }
}
