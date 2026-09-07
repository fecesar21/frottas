<?php

namespace Tests\Feature\Migrations;

use App\Models\Unidade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MigrarDadosLdapTest extends TestCase
{
    public function test_migracao_agrupa_valores_ad_por_unidade(): void
    {
        $unidade = Unidade::factory()->create();

        // A migration 2026_09_06_000007 já foi executada por RefreshDatabase,
        // logo a tabela foi dropada. Precisamos recreá-la para preparar os dados.
        if (!DB::getSchemaBuilder()->hasTable('unidade_ad_mapeamentos')) {
            DB::statement("
                CREATE TABLE unidade_ad_mapeamentos (
                    id TEXT PRIMARY KEY,
                    valor_ad TEXT NOT NULL UNIQUE,
                    unidade_id TEXT NOT NULL,
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY (unidade_id) REFERENCES unidades(id)
                )
            ");
        }

        DB::table('unidade_ad_mapeamentos')->insert([
            ['id' => (string) Str::uuid(), 'valor_ad' => 'HOSP-CENTRO', 'unidade_id' => $unidade->id, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'valor_ad' => 'HOSP-CENTRO-ANEXO', 'unidade_id' => $unidade->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

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
