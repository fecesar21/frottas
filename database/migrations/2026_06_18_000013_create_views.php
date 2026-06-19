<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_cnh_vencimento');
        DB::statement('DROP VIEW IF EXISTS vw_resumo_abastecimentos');

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
                DATEDIFF(cnh_validade, CURDATE()) AS dias_para_vencer
            FROM motoristas
            WHERE status = 'ativo'
            ORDER BY cnh_validade ASC
        ");

        DB::statement("
            CREATE VIEW vw_resumo_abastecimentos AS
            SELECT
                v.id         AS veiculo_id,
                v.placa,
                v.modelo,
                COUNT(a.id)                        AS total_abastecimentos,
                ROUND(SUM(a.litros), 2)            AS total_litros,
                ROUND(SUM(a.litros * a.valor_litro), 2) AS total_valor,
                ROUND(AVG(a.valor_litro), 3)       AS media_valor_litro,
                MAX(a.abastecido_at)               AS ultimo_abastecimento
            FROM veiculos v
            LEFT JOIN abastecimentos a ON a.veiculo_id = v.id
            GROUP BY v.id, v.placa, v.modelo
            ORDER BY total_valor DESC
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_cnh_vencimento');
        DB::statement('DROP VIEW IF EXISTS vw_resumo_abastecimentos');
    }
};
