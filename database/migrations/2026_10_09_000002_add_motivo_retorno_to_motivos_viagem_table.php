<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    // Motivo aplicado automaticamente à viagem de retorno à origem; nunca é oferecido para seleção.
    public function up(): void
    {
        if (DB::table('motivos_viagem')->where('codigo', 'retorno')->exists()) {
            return;
        }

        DB::table('motivos_viagem')->insert([
            'id' => (string) Str::uuid(), 'codigo' => 'retorno', 'nome' => 'Retorno', 'tipo_veiculo' => 'ambos',
            'disponivel_solicitacao' => false, 'ativo' => true, 'sistema' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('motivos_viagem')->where('codigo', 'retorno')->delete();
    }
};
