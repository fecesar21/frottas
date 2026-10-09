<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite não impõe o enum; ver 2026_08_05_000001.
            return;
        }

        DB::statement("ALTER TABLE usuarios MODIFY perfil ENUM('admin','gestor','operador','solicitante','dashboard') NOT NULL DEFAULT 'operador'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE usuarios MODIFY perfil ENUM('admin','gestor','operador','solicitante') NOT NULL DEFAULT 'operador'");
    }
};
