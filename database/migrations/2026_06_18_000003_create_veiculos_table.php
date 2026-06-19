<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veiculos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('placa', 10)->unique();
            $table->string('modelo', 100);
            $table->string('marca', 60);
            $table->smallInteger('ano');
            $table->string('cor', 30)->nullable();
            $table->string('chassi', 17)->nullable()->unique();
            $table->string('renavam', 11)->nullable()->unique();
            $table->enum('combustivel', ['diesel_s10', 'diesel_s500', 'gasolina', 'gasolina_aditivada', 'etanol', 'gnv', 'flex']);
            $table->unsignedSmallInteger('capacidade_tanque')->nullable();
            $table->unsignedInteger('km_atual')->default(0);
            $table->unsignedInteger('km_proxima_revisao')->nullable();
            $table->enum('status', ['disponivel', 'em_uso', 'manutencao', 'inativo'])->default('disponivel');
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculos');
    }
};
