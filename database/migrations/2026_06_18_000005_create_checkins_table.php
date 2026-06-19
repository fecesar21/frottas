<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('motorista_id');
            $table->uuid('veiculo_id');
            $table->uuid('escala_id')->nullable();
            $table->enum('turno', ['dia', 'noite']);
            $table->unsignedInteger('km_saida');
            $table->unsignedInteger('km_retorno')->nullable();
            $table->unsignedTinyInteger('nivel_combustivel_saida')->nullable()->comment('0-100%');
            $table->unsignedTinyInteger('nivel_combustivel_retorno')->nullable()->comment('0-100%');
            $table->timestamp('checkin_at');
            $table->timestamp('checkout_at')->nullable();
            $table->enum('status', ['ativo', 'encerrado'])->default('ativo');
            $table->text('ocorrencias')->nullable();
            $table->timestamps();

            $table->foreign('motorista_id')->references('id')->on('motoristas');
            $table->foreign('veiculo_id')->references('id')->on('veiculos');
            $table->foreign('escala_id')->references('id')->on('escalas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkins');
    }
};
