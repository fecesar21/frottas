<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viagens', function (Blueprint $table) {
            $table->string('motivo_viagem')->nullable()->after('destino');
            $table->unsignedInteger('numero_atendimento')->nullable()->after('motivo_viagem');
        });
    }

    public function down(): void
    {
        Schema::table('viagens', function (Blueprint $table) {
            $table->dropColumn(['motivo_viagem', 'numero_atendimento']);
        });
    }
};
