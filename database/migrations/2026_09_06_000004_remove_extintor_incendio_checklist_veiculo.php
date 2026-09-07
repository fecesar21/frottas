<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('checklist_veiculo_itens_modelo')
            ->where('label', 'Extintor de incêndio')
            ->update(['ativo' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('checklist_veiculo_itens_modelo')
            ->where('label', 'Extintor de incêndio')
            ->update(['ativo' => true, 'updated_at' => now()]);
    }
};
