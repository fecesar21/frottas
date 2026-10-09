<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Retornos à origem criados antes do motivo automático "retorno" herdaram o motivo da ida.
    public function up(): void
    {
        DB::table('viagens')->whereNotNull('viagem_ida_id')->where('motivo_viagem', '!=', 'retorno')
            ->update(['motivo_viagem' => 'retorno']);
    }

    public function down(): void
    {
        // Irreversível: o motivo original equivale ao da viagem de ida, mas não é restaurado automaticamente.
    }
};
