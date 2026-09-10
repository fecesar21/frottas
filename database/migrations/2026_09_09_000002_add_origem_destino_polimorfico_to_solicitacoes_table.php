<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->string('origem_tipo')->nullable()->after('origem_unidade_id');
            $table->string('destino_tipo')->nullable()->after('destino_unidade_id');
        });

        DB::table('solicitacoes')->whereNotNull('origem_unidade_id')->update(['origem_tipo' => 'unidade']);
        DB::table('solicitacoes')->whereNotNull('destino_unidade_id')->update(['destino_tipo' => 'unidade']);

        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->dropForeign(['origem_unidade_id']);
            $table->dropForeign(['destino_unidade_id']);
            $table->renameColumn('origem_unidade_id', 'origem_id');
            $table->renameColumn('destino_unidade_id', 'destino_id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->renameColumn('origem_id', 'origem_unidade_id');
            $table->renameColumn('destino_id', 'destino_unidade_id');
            $table->dropColumn(['origem_tipo', 'destino_tipo']);
        });

        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->foreign('origem_unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('destino_unidade_id')->references('id')->on('unidades')->nullOnDelete();
        });
    }
};
