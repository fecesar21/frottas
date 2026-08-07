<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viagem_pontos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('viagem_id')->constrained('viagens')->cascadeOnDelete();
            $table->decimal('latitude',  10, 7);
            $table->decimal('longitude', 10, 7);
            $table->float('accuracy')->nullable();
            $table->timestamp('capturado_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viagem_pontos');
    }
};
