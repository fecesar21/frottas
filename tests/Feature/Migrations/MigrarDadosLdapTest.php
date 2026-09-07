<?php

namespace Tests\Feature\Migrations;

use App\Models\Unidade;
use App\Models\UnidadeAdMapeamento;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigrarDadosLdapTest extends TestCase
{
    public function test_migracao_agrupa_valores_ad_por_unidade(): void
    {
        $unidade = Unidade::factory()->create();
        UnidadeAdMapeamento::create(['valor_ad' => 'HOSP-CENTRO', 'unidade_id' => $unidade->id]);
        UnidadeAdMapeamento::create(['valor_ad' => 'HOSP-CENTRO-ANEXO', 'unidade_id' => $unidade->id]);

        // RefreshDatabase já executou todas as migrations (incluindo esta)
        // antes deste teste criar os dados acima. Como a migration já
        // consta na tabela `migrations`, `artisan migrate` não a
        // re-executaria por padrão — removemos o registro para forçar a
        // re-execução isolada desta migration, o que é seguro porque seu
        // `updateOrInsert` é idempotente.
        DB::table('migrations')
            ->where('migration', '2026_09_06_000006_migrate_dados_ldap_para_unidade_ldap_configuracoes')
            ->delete();

        \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_09_06_000006_migrate_dados_ldap_para_unidade_ldap_configuracoes.php',
            '--force' => true,
        ]);

        $config = DB::table('unidade_ldap_configuracoes')->where('unidade_id', $unidade->id)->first();
        $this->assertNotNull($config);
        $valores = json_decode($config->valores_ad, true);
        sort($valores);
        $this->assertSame(['HOSP-CENTRO', 'HOSP-CENTRO-ANEXO'], $valores);
    }
}
