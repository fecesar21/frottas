<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veiculo_manutencoes', function (Blueprint $table) {
            $table->string('tipo', 30)->nullable()->after('fim');
            $table->string('origem', 20)->default('gestor')->after('motivo');
            $table->uuid('aberta_por_id')->nullable()->after('origem');
            $table->uuid('fechada_por_id')->nullable()->after('aberta_por_id');
            $table->unsignedInteger('km_entrada')->nullable()->after('fechada_por_id');
            $table->unsignedInteger('km_saida')->nullable()->after('km_entrada');
            $table->string('local', 150)->nullable()->after('km_saida');
            $table->string('observacao_saida', 255)->nullable()->after('local');

            $table->foreign('aberta_por_id')->references('id')->on('usuarios')->nullOnDelete();
            $table->foreign('fechada_por_id')->references('id')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('veiculo_manutencoes', function (Blueprint $table) {
            $table->dropForeign(['aberta_por_id']);
            $table->dropForeign(['fechada_por_id']);
            $table->dropColumn(['tipo', 'origem', 'aberta_por_id', 'fechada_por_id', 'km_entrada', 'km_saida', 'local', 'observacao_saida']);
        });
    }
};
