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
            ['cpf' => '31996295802'],
            [
                'nome'       => 'Fernando César Cardoso',
                'email'      => 'admin@admin.com',
                'senha_hash' => Hash::make('28052025'),
                'perfil'     => 'admin',
                'ativo'      => true,
            ]
        );

        Usuario::firstOrCreate(
            ['cpf' => '00000000002'],
            [
                'nome'       => 'Amadeu Lima',
                'email'      => 'amadeu.lima@isac.org.br',
                'senha_hash' => Hash::make('123456'),
                'perfil'     => 'gestor',
                'ativo'      => true,
            ]
        );
    }
}
