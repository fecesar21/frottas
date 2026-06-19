<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('motorista_id');
            $table->uuid('veiculo_id')->nullable();
            $table->date('data');
            $table->enum('turno', ['dia', 'noite', 'folga']);
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->foreign('motorista_id')->references('id')->on('motoristas');
            $table->foreign('veiculo_id')->references('id')->on('veiculos');
            $table->unique(['motorista_id', 'data', 'turno']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalas');
    }
};
