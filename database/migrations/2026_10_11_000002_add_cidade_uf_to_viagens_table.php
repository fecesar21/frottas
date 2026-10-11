<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viagens', function (Blueprint $table) {
            // Cidade/UF de destino, obrigatória quando o motivo é TFD.
            $table->string('cidade_uf', 100)->nullable()->after('numero_atendimento');
        });
    }

    public function down(): void
    {
        Schema::table('viagens', function (Blueprint $table) {
            $table->dropColumn('cidade_uf');
        });
    }
};
