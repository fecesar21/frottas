<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            //$table->uuid('id')->primary();
            $table->foreignId('motorista_id')->nullable()->constrained('motoristas')->onDelete('set null');
            $table->string('nome', 100);
            $table->string('email', 100)->nullable()->unique();
            $table->string('senha_hash');
            $table->enum('perfil', ['admin', 'gestor', 'operador'])->default('operador');
            $table->boolean('ativo')->default(true);
            $table->timestamp('ultimo_acesso')->nullable();
            $table->uuid('motorista_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
