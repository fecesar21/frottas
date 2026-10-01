<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Motoristas que alternam entre ambulância e carro administrativo no mesmo
        // plantão podem manter até 2 check-ins ativos em veículos distintos.
        Schema::table('motoristas', function (Blueprint $table) {
            $table->boolean('permite_checkin_duplo')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('motoristas', function (Blueprint $table) {
            $table->dropColumn('permite_checkin_duplo');
        });
    }
};
