<?php

use App\Models\Unidade;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('unidade_ad_mapeamentos')) {
            return;
        }

        $mapeamentos = DB::table('unidade_ad_mapeamentos')->get()->groupBy('unidade_id');

        if ($mapeamentos->isEmpty()) {
            return;
        }

        $conexao = config('ldap.connections.default', []);

        foreach ($mapeamentos as $unidadeId => $linhas) {
            if (! Unidade::whereKey($unidadeId)->exists()) {
                continue;
            }

            DB::table('unidade_ldap_configuracoes')->updateOrInsert(
                ['unidade_id' => $unidadeId],
                [
                    'id' => (string) Str::uuid(),
                    'host' => $conexao['hosts'][0] ?? '',
                    'port' => $conexao['port'] ?? 636,
                    'base_dn' => $conexao['base_dn'] ?? '',
                    'username' => $conexao['username'] ?? '',
                    'password' => encrypt($conexao['password'] ?? ''),
                    'use_ssl' => $conexao['use_tls'] ?? true,
                    'use_starttls' => $conexao['use_starttls'] ?? false,
                    'unidade_attribute' => config('ldap.unidade_attribute', 'department'),
                    'valores_ad' => json_encode($linhas->pluck('valor_ad')->values()->all()),
                    'ativo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        // Dados migrados não são revertidos automaticamente — a tabela de
        // origem (unidade_ad_mapeamentos) permanece intacta até a migration
        // de remoção (Task 5), que é o ponto de não-retorno real.
    }
};
