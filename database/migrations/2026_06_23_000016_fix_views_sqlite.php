<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Garante a remoção da view anterior para evitar conflitos de "View already exists"
        DB::statement('DROP VIEW IF EXISTS vw_cnh_vencimento');

        // Detecta o driver ativo (mysql, sqlite, pgsql, etc.)
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement("
                CREATE VIEW vw_cnh_vencimento AS
                SELECT
                    id,
                    nome,
                    cpf,
                    cnh_numero,
                    cnh_categoria,
                    cnh_validade,
                    status,
                    CAST(julianday(cnh_validade) - julianday('now') AS INTEGER) AS dias_para_vencer
                FROM motoristas
                WHERE status = 'ativo'
            ");
        } else {
            // Sintaxe nativa para MySQL / MariaDB
            DB::statement("
                CREATE VIEW vw_cnh_vencimento AS
                SELECT
                    id,
                    nome,
                    cpf,
                    cnh_numero,
                    cnh_categoria,
                    cnh_validade,
                    status,
                    DATEDIFF(cnh_validade, NOW()) AS dias_para_vencer
                FROM motoristas
                WHERE status = 'ativo'
            ");
        }
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_cnh_vencimento');
    }
};
