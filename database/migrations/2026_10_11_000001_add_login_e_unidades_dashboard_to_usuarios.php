<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Usuário de login comum (sem CPF), usado pelo perfil "dashboard".
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('login', 60)->nullable()->unique()->after('cpf');
        });

        // Unidades que um usuário do perfil "dashboard" pode visualizar.
        Schema::create('usuario_unidades', function (Blueprint $table) {
            $table->uuid('usuario_id');
            $table->uuid('unidade_id');
            $table->primary(['usuario_id', 'unidade_id']);
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->foreign('unidade_id')->references('id')->on('unidades')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_unidades');
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropUnique(['login']);
            $table->dropColumn('login');
        });
    }
};
