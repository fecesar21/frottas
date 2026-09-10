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

        $this->dropForeignKeyFor('origem_unidade_id');
        $this->dropForeignKeyFor('destino_unidade_id');

        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->renameColumn('origem_unidade_id', 'origem_id');
            $table->renameColumn('destino_unidade_id', 'destino_id');
        });
    }

    /**
     * Remove a FK apontando para a coluna, buscando o nome real da constraint
     * em vez de assumir o padrao do Laravel (producao renomeou algumas apos
     * uma migration anterior que recriou a tabela com sufixo "_tmp_20260807").
     */
    private function dropForeignKeyFor(string $column): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'mysql') {
            Schema::table('solicitacoes', function (Blueprint $table) use ($column) {
                $table->dropForeign([$column]);
            });

            return;
        }

        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? '
            .'AND REFERENCED_TABLE_NAME IS NOT NULL',
            ['solicitacoes', $column]
        );

        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE solicitacoes DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
        }
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
