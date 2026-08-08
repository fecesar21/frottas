<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklists_veiculo', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('veiculo_id');
            $table->uuid('motorista_id');
            $table->uuid('checkin_id');
            $table->date('data_referencia');
            $table->enum('status', ['pendente', 'enviado'])->default('pendente');
            $table->unsignedSmallInteger('itens_conforme')->default(0);
            $table->unsignedSmallInteger('itens_nao_conforme')->default(0);
            $table->text('observacoes_gerais')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamps();

            $table->foreign('veiculo_id')->references('id')->on('veiculos')->cascadeOnDelete();
            $table->foreign('motorista_id')->references('id')->on('motoristas')->cascadeOnDelete();
            $table->foreign('checkin_id')->references('id')->on('checkins')->cascadeOnDelete();

            $table->unique(['veiculo_id', 'data_referencia']);
            $table->index('motorista_id');
            $table->index('data_referencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklists_veiculo');
    }
};
