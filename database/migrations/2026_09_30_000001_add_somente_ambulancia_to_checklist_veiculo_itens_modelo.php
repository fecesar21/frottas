<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_veiculo_itens_modelo', function (Blueprint $table) {
            $table->boolean('somente_ambulancia')->default(false)->after('valor_max');
        });

        // Carros administrativos não têm cilindro de oxigênio.
        DB::table('checklist_veiculo_itens_modelo')
            ->where('label', 'Nível de Oxigênio')
            ->update(['somente_ambulancia' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('checklist_veiculo_itens_modelo', function (Blueprint $table) {
            $table->dropColumn('somente_ambulancia');
        });
    }
};
