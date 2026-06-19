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
            ['email' => 'admin@healthdrive.com.br'],
            [
                'nome'       => 'Administrador',
                'senha_hash' => Hash::make('admin123'),
                'perfil'     => 'admin',
                'ativo'      => true,
            ]
        );

        Usuario::firstOrCreate(
            ['email' => 'gestor@healthdrive.com.br'],
            [
                'nome'       => 'Gestor de Frota',
                'senha_hash' => Hash::make('gestor123'),
                'perfil'     => 'gestor',
                'ativo'      => true,
            ]
        );
    }
}
