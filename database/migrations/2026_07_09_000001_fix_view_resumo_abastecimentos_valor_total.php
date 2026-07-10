<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_resumo_abastecimentos');

        DB::statement('
            CREATE VIEW vw_resumo_abastecimentos AS
            SELECT
                v.id            AS veiculo_id,
                v.placa,
                v.modelo,
                COUNT(a.id)                                 AS total_abastecimentos,
                ROUND(SUM(a.litros), 2)                    AS total_litros,
                ROUND(SUM(a.litros * a.valor_litro), 2)    AS total_valor,
                ROUND(AVG(a.valor_litro), 3)               AS media_valor_litro,
                MAX(a.abastecido_at)                       AS ultimo_abastecimento
            FROM veiculos v
            LEFT JOIN abastecimentos a ON a.veiculo_id = v.id AND a.deleted_at IS NULL
            GROUP BY v.id, v.placa, v.modelo
        ');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_resumo_abastecimentos');

        DB::statement('
            CREATE VIEW vw_resumo_abastecimentos AS
            SELECT
                v.id            AS veiculo_id,
                v.placa,
                v.modelo,
                COUNT(a.id)                                 AS total_abastecimentos,
                ROUND(SUM(a.litros), 2)                    AS total_litros,
                ROUND(SUM(a.valor_total), 2)               AS total_valor,
                ROUND(AVG(a.valor_litro), 3)               AS media_valor_litro,
                MAX(a.abastecido_at)                       AS ultimo_abastecimento
            FROM veiculos v
            LEFT JOIN abastecimentos a ON a.veiculo_id = v.id AND a.deleted_at IS NULL
            GROUP BY v.id, v.placa, v.modelo
        ');
    }
};
