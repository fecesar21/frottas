<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passagens_plantao', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('veiculo_id');
            $table->uuid('motorista_saindo_id');
            $table->uuid('motorista_entrando_id');
            $table->uuid('checkin_saida_id')->nullable();
            $table->uuid('checkin_entrada_id')->nullable();
            $table->enum('turno_saindo', ['dia', 'noite']);
            $table->enum('turno_entrando', ['dia', 'noite']);
            $table->unsignedInteger('km_momento')->nullable();
            $table->unsignedTinyInteger('nivel_combustivel')->nullable()->comment('0-100%');
            $table->unsignedSmallInteger('total_itens')->default(0);
            $table->unsignedSmallInteger('itens_ok')->default(0);
            $table->unsignedSmallInteger('itens_pendencia')->default(0);
            $table->text('observacoes_gerais')->nullable();
            $table->timestamp('finalizado_at')->nullable();
            $table->timestamps();

            $table->foreign('veiculo_id')->references('id')->on('veiculos');
            $table->foreign('motorista_saindo_id')->references('id')->on('motoristas');
            $table->foreign('motorista_entrando_id')->references('id')->on('motoristas');
            $table->foreign('checkin_saida_id')->references('id')->on('checkins')->nullOnDelete();
            $table->foreign('checkin_entrada_id')->references('id')->on('checkins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passagens_plantao');
    }
};
