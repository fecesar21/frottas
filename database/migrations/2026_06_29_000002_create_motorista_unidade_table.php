<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motorista_unidade', function (Blueprint $table) {
            $table->uuid('motorista_id');
            $table->uuid('unidade_id');

            $table->foreign('motorista_id')->references('id')->on('motoristas')->cascadeOnDelete();
            $table->foreign('unidade_id')->references('id')->on('unidades')->cascadeOnDelete();

            $table->primary(['motorista_id', 'unidade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motorista_unidade');
    }
};
