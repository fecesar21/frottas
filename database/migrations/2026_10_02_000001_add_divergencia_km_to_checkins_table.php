<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkins', function (Blueprint $table) {
            $table->unsignedInteger('km_retorno_esperado')->nullable()->after('km_retorno');
            $table->integer('divergencia_km')->nullable()->after('km_retorno_esperado');
            $table->text('justificativa_divergencia_km')->nullable()->after('divergencia_km');
        });
    }

    public function down(): void
    {
        Schema::table('checkins', function (Blueprint $table) {
            $table->dropColumn(['km_retorno_esperado', 'divergencia_km', 'justificativa_divergencia_km']);
        });
    }
};
