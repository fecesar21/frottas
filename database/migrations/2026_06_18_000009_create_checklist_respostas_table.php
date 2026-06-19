<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_respostas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('passagem_id');
            $table->unsignedBigInteger('item_modelo_id');
            $table->enum('resultado', ['ok', 'pendencia', 'nao_verificado'])->default('nao_verificado');
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->foreign('passagem_id')->references('id')->on('passagens_plantao')->cascadeOnDelete();
            $table->foreign('item_modelo_id')->references('id')->on('checklist_itens_modelo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_respostas');
    }
};
