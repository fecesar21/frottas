<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 80);
            $table->unsignedTinyInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_categorias');
    }
};
