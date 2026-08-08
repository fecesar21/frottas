<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_veiculo_respostas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('checklist_veiculo_id');
            $table->unsignedBigInteger('item_modelo_id');
            $table->boolean('conforme')->nullable();
            $table->text('observacao')->nullable();
            $table->string('foto_path', 255)->nullable();
            $table->timestamps();

            $table->foreign('checklist_veiculo_id')->references('id')->on('checklists_veiculo')->cascadeOnDelete();
            $table->foreign('item_modelo_id')->references('id')->on('checklist_veiculo_itens_modelo');

            $table->unique(['checklist_veiculo_id', 'item_modelo_id'], 'cv_respostas_checklist_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_veiculo_respostas');
    }
};
