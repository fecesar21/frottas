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

        // Os valores do conector legado só existem no .env do servidor (o
        // config/ldap.php que os lia foi removido nesta mesma branch pela
        // Task 6 de limpeza) — ler config() aqui resolveria sempre para os
        // defaults vazios. Ler env() diretamente preserva a cópia pontual
        // dos dados de produção conforme o design original.
        $host = env('LDAP_HOST', '');

        foreach ($mapeamentos as $unidadeId => $linhas) {
            if (! Unidade::whereKey($unidadeId)->exists()) {
                continue;
            }

            DB::table('unidade_ldap_configuracoes')->updateOrInsert(
                ['unidade_id' => $unidadeId],
                [
                    'id' => (string) Str::uuid(),
                    'host' => $host,
                    'port' => (int) env('LDAP_PORT', 636),
                    'base_dn' => env('LDAP_BASE_DN', ''),
                    'username' => env('LDAP_USERNAME', ''),
                    'password' => encrypt(env('LDAP_PASSWORD', '')),
                    // Mapeamento preservado do antigo config/ldap.php: lá,
                    // LDAP_USE_SSL alimentava a opção use_tls do LdapRecord
                    // e LDAP_USE_TLS alimentava use_starttls (invertido em
                    // relação ao nome das variáveis, mas deliberado).
                    'use_ssl' => filter_var(env('LDAP_USE_SSL', true), FILTER_VALIDATE_BOOLEAN),
                    'use_starttls' => filter_var(env('LDAP_USE_TLS', false), FILTER_VALIDATE_BOOLEAN),
                    'unidade_attribute' => env('LDAP_UNIDADE_ATTRIBUTE', 'department'),
                    'valores_ad' => json_encode($linhas->pluck('valor_ad')->values()->all()),
                    'ativo' => $host !== '',
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
