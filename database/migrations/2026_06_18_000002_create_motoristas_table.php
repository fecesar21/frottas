<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motoristas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome', 100);
            $table->string('cpf', 14)->unique();
            $table->string('telefone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('cnh_numero', 20)->unique();
            $table->string('cnh_categoria', 5);
            $table->date('cnh_validade');
            $table->enum('turno_padrao', ['dia', 'noite'])->nullable();
            $table->enum('status', ['ativo', 'inativo', 'ferias', 'afastado'])->default('ativo');
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motoristas');
    }
};
