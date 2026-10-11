<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viagens', function (Blueprint $table) {
            $table->boolean('km_suspeito')->default(false)->after('km_chegada');
            $table->string('justificativa_km', 500)->nullable()->after('km_suspeito');
        });
    }

    public function down(): void
    {
        Schema::table('viagens', function (Blueprint $table) {
            $table->dropColumn(['km_suspeito', 'justificativa_km']);
        });
    }
};
