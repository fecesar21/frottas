<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('unidade_ad_mapeamentos');
    }

    public function down(): void
    {
        Schema::create('unidade_ad_mapeamentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('valor_ad')->unique();
            $table->foreignUuid('unidade_id')->constrained('unidades');
            $table->timestamps();
        });
    }
};
