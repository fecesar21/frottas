<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viagens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('veiculo_id');
            $table->uuid('motorista_id');
            $table->uuid('checkin_id')->nullable();
            $table->string('origem', 150);
            $table->string('destino', 150);
            $table->unsignedInteger('km_saida');
            $table->unsignedInteger('km_chegada')->nullable();
            $table->timestamp('saida_at');
            $table->timestamp('chegada_at')->nullable();
            $table->enum('status', ['em_andamento', 'concluida', 'cancelada'])->default('em_andamento');
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->foreign('veiculo_id')->references('id')->on('veiculos');
            $table->foreign('motorista_id')->references('id')->on('motoristas');
            $table->foreign('checkin_id')->references('id')->on('checkins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viagens');
    }
};
