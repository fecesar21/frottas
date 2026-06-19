<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abastecimentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('veiculo_id');
            $table->uuid('motorista_id');
            $table->uuid('checkin_id')->nullable();
            $table->string('posto', 100)->nullable();
            $table->enum('combustivel', ['diesel_s10', 'diesel_s500', 'gasolina', 'gasolina_aditivada', 'etanol', 'gnv', 'flex']);
            $table->decimal('litros', 8, 3);
            $table->decimal('valor_litro', 7, 3);
            $table->unsignedInteger('km_momento');
            $table->timestamp('abastecido_at');
            $table->string('nota_fiscal', 60)->nullable();
            $table->text('observacoes')->nullable();

            $table->foreign('veiculo_id')->references('id')->on('veiculos');
            $table->foreign('motorista_id')->references('id')->on('motoristas');
            $table->foreign('checkin_id')->references('id')->on('checkins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abastecimentos');
    }
};
