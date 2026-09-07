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

    public function test_migracao_le_credenciais_do_env_e_e_idempotente(): void
    {
        // As chaves LDAP_* já existem (vazias) no .env de teste, e o
        // repositório do Dotenv usado pelo Laravel é imutável por padrão
        // para variáveis já carregadas — putenv()/$_ENV sozinhos não
        // seriam enxergados por env(). Escrevemos diretamente no
        // repositório do Env para forçar a sobrescrita nesta suíte.
        $repo = \Illuminate\Support\Env::getRepository();
        putenv('LDAP_HOST=ldap.exemplo.com.br');
        putenv('LDAP_USERNAME=svc_ldap');
        putenv('LDAP_PASSWORD=segredo-super-secreto');
        $_ENV['LDAP_HOST'] = 'ldap.exemplo.com.br';
        $_ENV['LDAP_USERNAME'] = 'svc_ldap';
        $_ENV['LDAP_PASSWORD'] = 'segredo-super-secreto';
        $repo->set('LDAP_HOST', 'ldap.exemplo.com.br');
        $repo->set('LDAP_USERNAME', 'svc_ldap');
        $repo->set('LDAP_PASSWORD', 'segredo-super-secreto');

        try {
            $unidade = Unidade::factory()->create();

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
                ['id' => (string) Str::uuid(), 'valor_ad' => 'HOSP-NORTE', 'unidade_id' => $unidade->id, 'created_at' => now(), 'updated_at' => now()],
            ]);

            DB::table('migrations')
                ->where('migration', '2026_09_06_000006_migrate_dados_ldap_para_unidade_ldap_configuracoes')
                ->delete();

            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--path' => 'database/migrations/2026_09_06_000006_migrate_dados_ldap_para_unidade_ldap_configuracoes.php',
                '--force' => true,
            ]);

            $config = DB::table('unidade_ldap_configuracoes')->where('unidade_id', $unidade->id)->first();
            $this->assertNotNull($config);
            $this->assertSame('ldap.exemplo.com.br', $config->host);
            $this->assertSame('svc_ldap', $config->username);
            $this->assertSame('segredo-super-secreto', decrypt($config->password));
            $this->assertEquals(1, $config->ativo);

            // Segunda execução: força novo re-run da mesma migration e
            // garante que o updateOrInsert não duplica a linha nem altera
            // os valores já persistidos.
            DB::table('migrations')
                ->where('migration', '2026_09_06_000006_migrate_dados_ldap_para_unidade_ldap_configuracoes')
                ->delete();

            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--path' => 'database/migrations/2026_09_06_000006_migrate_dados_ldap_para_unidade_ldap_configuracoes.php',
                '--force' => true,
            ]);

            $configs = DB::table('unidade_ldap_configuracoes')->where('unidade_id', $unidade->id)->get();
            $this->assertCount(1, $configs);
            $configAposRerun = $configs->first();
            $this->assertSame('ldap.exemplo.com.br', $configAposRerun->host);
            $this->assertSame('svc_ldap', $configAposRerun->username);
            $this->assertSame('segredo-super-secreto', decrypt($configAposRerun->password));
        } finally {
            $repo->set('LDAP_HOST', '');
            $repo->set('LDAP_USERNAME', '');
            $repo->set('LDAP_PASSWORD', '');
            putenv('LDAP_HOST');
            putenv('LDAP_USERNAME');
            putenv('LDAP_PASSWORD');
            unset($_ENV['LDAP_HOST'], $_ENV['LDAP_USERNAME'], $_ENV['LDAP_PASSWORD']);
        }
    }
}
