<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite não impõe o enum de fato (é um CHECK constraint), então
            // não há coluna a alterar via schema builder; o valor extra já é
            // aceito pela coluna TEXT subjacente. Nada a fazer aqui além de
            // registrar a migration para manter o histórico em sincronia
            // entre ambientes.
            return;
        }

        DB::statement("ALTER TABLE usuarios MODIFY perfil ENUM('admin','gestor','operador','solicitante') NOT NULL DEFAULT 'operador'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE usuarios MODIFY perfil ENUM('admin','gestor','operador') NOT NULL DEFAULT 'operador'");
    }
};
