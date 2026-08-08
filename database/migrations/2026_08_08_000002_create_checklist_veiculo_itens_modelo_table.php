<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_veiculo_itens_modelo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('categoria_id');
            $table->string('label', 100);
            $table->string('descricao', 255)->nullable();
            $table->tinyInteger('ordem')->default(0);
            $table->boolean('obrigatorio')->default(true);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->foreign('categoria_id')->references('id')->on('checklist_veiculo_categorias')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_veiculo_itens_modelo');
    }
};
