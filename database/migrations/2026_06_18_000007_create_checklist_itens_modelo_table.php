<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_itens_modelo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('categoria_id');
            $table->string('label', 100);
            $table->string('descricao', 255)->nullable();
            $table->unsignedTinyInteger('ordem')->default(0);
            $table->boolean('obrigatorio')->default(false);
            $table->boolean('ativo')->default(true);

            $table->foreign('categoria_id')->references('id')->on('checklist_categorias')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_itens_modelo');
    }
};
