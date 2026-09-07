<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('checklist_veiculo_itens_modelo')
            ->where('label', 'Triângulo de segurança')
            ->update([
                'label' => 'Nível de Oxigênio',
                'requer_valor' => true,
                'valor_min' => 0,
                'valor_max' => 300,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('checklist_veiculo_itens_modelo')
            ->where('label', 'Nível de Oxigênio')
            ->update([
                'label' => 'Triângulo de segurança',
                'requer_valor' => false,
                'valor_min' => null,
                'valor_max' => null,
                'updated_at' => now(),
            ]);
    }
};
