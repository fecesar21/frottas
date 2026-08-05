<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id');
            $table->uuid('unidade_id')->nullable();
            $table->string('motivo');
            $table->uuid('origem_unidade_id')->nullable();
            $table->uuid('destino_unidade_id')->nullable();
            $table->unsignedInteger('numero_atendimento')->nullable();
            $table->string('cidade', 150)->nullable();
            $table->string('hospital_destino', 150)->nullable();
            $table->string('fornecedor_nome', 150)->nullable();
            $table->enum('status', ['aberto', 'em_trajeto', 'finalizado', 'cancelado'])->default('aberto');
            $table->uuid('viagem_id')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('usuarios');
            $table->foreign('unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('origem_unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('destino_unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('viagem_id')->references('id')->on('viagens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacoes');
    }
};
