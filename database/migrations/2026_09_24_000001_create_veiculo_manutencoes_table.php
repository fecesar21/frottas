<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('veiculo_manutencoes')) {
            Schema::create('veiculo_manutencoes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('veiculo_id');
                $table->dateTime('inicio');
                $table->dateTime('fim')->nullable();
                $table->string('motivo', 255)->nullable();
                $table->timestamps();

                $table->foreign('veiculo_id')->references('id')->on('veiculos')->cascadeOnDelete();
                $table->index(['veiculo_id', 'fim']);
                $table->index('inicio');
            });
        }

        // Veículos que já estão em manutenção ganham um registro aberto, para
        // que o tempo corrente não se perca ao sair da manutenção.
        $abertos = DB::table('veiculo_manutencoes')->whereNull('fim')->pluck('veiculo_id');

        DB::table('veiculos')
            ->where('status', 'manutencao')
            ->whereNotIn('id', $abertos)
            ->get(['id', 'manutencao_inicio', 'updated_at'])
            ->each(fn ($v) => DB::table('veiculo_manutencoes')->insert([
                'id' => (string) Str::uuid(),
                'veiculo_id' => $v->id,
                'inicio' => $v->manutencao_inicio ?? $v->updated_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('veiculo_manutencoes');
    }
};
