<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veiculo_unidade', function (Blueprint $table) {
            $table->uuid('veiculo_id');
            $table->uuid('unidade_id');

            $table->foreign('veiculo_id')->references('id')->on('veiculos')->cascadeOnDelete();
            $table->foreign('unidade_id')->references('id')->on('unidades')->cascadeOnDelete();

            $table->primary(['veiculo_id', 'unidade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculo_unidade');
    }
};
