<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUS = ['aberto', 'pendente_motorista', 'em_trajeto', 'aguardando_finalizacao_trajeto', 'recusada', 'finalizado', 'cancelado'];

    public function up(): void
    {
        $this->definirStatus([...self::STATUS, 'recusada_gestao']);
    }

    public function down(): void
    {
        DB::table('solicitacoes')->where('status', 'recusada_gestao')->update(['status' => 'cancelado']);
        $this->definirStatus(self::STATUS);
    }

    /**
     * No MySQL o status é ENUM e precisa ser redefinido. No SQLite (dev/testes)
     * o enum vira CHECK, então a coluna é recriada como enum via change().
     */
    private function definirStatus(array $valores): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $lista = implode(', ', array_map(fn ($v) => "'{$v}'", $valores));
            DB::statement("ALTER TABLE solicitacoes MODIFY status ENUM({$lista}) NOT NULL DEFAULT 'aberto'");

            return;
        }

        Schema::table('solicitacoes', function ($table) use ($valores) {
            $table->enum('status', $valores)->default('aberto')->change();
        });
    }
};
