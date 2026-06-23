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
            ['email' => 'fernando.cardoso@isac.org.br'],
            [
                'nome'       => 'Fernando César Cardoso',
                'senha_hash' => Hash::make('Fer$2025'),
                'perfil'     => 'admin',
                'ativo'      => true,
            ]
        );

        Usuario::firstOrCreate(
            ['email' => 'amadeu.lima@isac.org.br'],
            [
                'nome'       => 'Amadeu Lima',
                'senha_hash' => Hash::make('gestor123'),
                'perfil'     => 'gestor',
                'ativo'      => true,
            ]
        );
    }
}
