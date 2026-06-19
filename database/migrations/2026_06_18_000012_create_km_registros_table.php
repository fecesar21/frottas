<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('km_registros', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('veiculo_id');
            $table->uuid('motorista_id')->nullable();
            $table->uuid('checkin_id')->nullable();
            $table->unsignedInteger('km_anterior');
            $table->unsignedInteger('km_atual');
            $table->text('observacao')->nullable();
            $table->timestamp('registrado_at');

            $table->foreign('veiculo_id')->references('id')->on('veiculos');
            $table->foreign('motorista_id')->references('id')->on('motoristas')->nullOnDelete();
            $table->foreign('checkin_id')->references('id')->on('checkins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('km_registros');
    }
};
