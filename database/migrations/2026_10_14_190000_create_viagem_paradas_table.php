<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viagem_paradas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('viagem_id')->constrained('viagens')->cascadeOnDelete();
            $table->unsignedSmallInteger('ordem');
            $table->string('local', 150);
            $table->string('motivo', 250)->nullable();
            $table->timestamp('chegada_at');
            $table->timestamp('saida_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viagem_paradas');
    }
};
