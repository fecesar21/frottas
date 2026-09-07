# Configurações Admin: LDAP por Unidade — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give Admins a "Configurações" screen to manage one LDAP/AD connection per Unidade, and make the AD login endpoint try every active unit's connection instead of a single hardcoded one.

**Architecture:** New table `unidade_ldap_configuracoes` (1:1 with `unidades`, encrypted password) replaces both the `.env`-driven `config/ldap.php` connection and the `unidade_ad_mapeamentos` table. `AuthController@loginAd` iterates active configs, registering each as a dynamic LdapRecord connection and attempting a bind, until one succeeds. Admin-only REST endpoints (CRUD + a non-persisting "test connection" action) back a new frontend page under a generic `/configuracoes` hub.

**Tech Stack:** Laravel 11, `directorytree/ldaprecord-laravel` ^4.0, SQLite (tests + default dev DB), React + `@tanstack/react-query`, Tailwind, `lucide-react` icons.

**Spec:** `docs/superpowers/specs/2026-09-06-configuracoes-ldap-por-unidade-design.md`

## Global Constraints

- All models use UUID primary keys via `HasUuids` (`$incrementing = false`, `$keyType = 'string'`) — match existing `Unidade`/`UnidadeAdMapeamento` pattern.
- Admin-only backend routes go inside the existing `Route::middleware('admin')` group in `routes/api.php` (alias `admin` → `App\Http\Middleware\SomenteAdmin`, registered in `bootstrap/app.php`).
- Password field must never be serialized back to the client (`$hidden`), and must be stored via Eloquent's `encrypted` cast — never plaintext.
- Every backend task must leave `php artisan test` green before moving to the next task.
- Frontend must reuse existing shared components (`Modal`, `Alert`, `LoadingSpinner`, `Card`) and existing styling conventions (Tailwind utility classes matching `UnidadesList.jsx`/`UnidadeForm.jsx`) rather than introducing new UI primitives.
- Commit after every task (see per-task commit step).

---

## File Structure

**Backend — create:**
- `database/migrations/2026_09_06_000005_create_unidade_ldap_configuracoes_table.php`
- `database/migrations/2026_09_06_000006_migrate_dados_ldap_para_unidade_ldap_configuracoes.php`
- `database/migrations/2026_09_06_000007_drop_unidade_ad_mapeamentos_table.php`
- `app/Models/UnidadeLdapConfiguracao.php`
- `database/factories/UnidadeLdapConfiguracaoFactory.php`
- `app/Http/Requests/UnidadeLdapConfig/UpsertUnidadeLdapConfigRequest.php`
- `app/Http/Controllers/Api/UnidadeLdapConfigController.php`
- `tests/Feature/Unidade/UnidadeLdapConfigApiTest.php`
- `tests/Feature/Unidade/TestarConexaoLdapTest.php`

**Backend — modify:**
- `app/Http/Controllers/Api/AuthController.php` (rewrite `loginAd`)
- `routes/api.php` (new admin routes)
- `tests/Feature/Auth/LoginAdTest.php` (rewrite for multi-unit)

**Backend — remove:**
- `app/Models/UnidadeAdMapeamento.php` (after data migration, once nothing references it)
- `config/ldap.php` (no longer read at runtime)

**Frontend — create:**
- `resources/js/api/unidadeLdapConfig.js`
- `resources/js/pages/configuracoes/ConfiguracoesHub.jsx`
- `resources/js/pages/configuracoes/ConfiguracoesLdap.jsx`
- `resources/js/pages/configuracoes/UnidadeLdapConfigForm.jsx`

**Frontend — modify:**
- `resources/js/components/layout/Sidebar.jsx` (new ADMIN menu item)
- `resources/js/FleetApp.jsx` (new routes)
- `resources/js/components/layout/Layout.jsx` (new `pageTitles` entries)

---

### Task 1: Migration + Model for `UnidadeLdapConfiguracao`

**Files:**
- Create: `database/migrations/2026_09_06_000005_create_unidade_ldap_configuracoes_table.php`
- Create: `app/Models/UnidadeLdapConfiguracao.php`
- Create: `database/factories/UnidadeLdapConfiguracaoFactory.php`
- Test: `tests/Unit/Models/UnidadeLdapConfiguracaoTest.php`

**Interfaces:**
- Produces: `App\Models\UnidadeLdapConfiguracao` with fillable fields `unidade_id, host, port, base_dn, username, password, use_ssl, use_starttls, unidade_attribute, valores_ad, ativo`; casts `password => encrypted`, `valores_ad => array`, `use_ssl|use_starttls|ativo => boolean`; `$hidden = ['password']`; relation `unidade(): BelongsTo`; method `paraConexaoLdap(): array` returning an LdapRecord connection config array.

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidade_ldap_configuracoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('unidade_id')->unique()->constrained('unidades')->cascadeOnDelete();
            $table->string('host');
            $table->unsignedInteger('port')->default(636);
            $table->string('base_dn');
            $table->string('username');
            $table->text('password');
            $table->boolean('use_ssl')->default(true);
            $table->boolean('use_starttls')->default(false);
            $table->string('unidade_attribute')->default('department');
            $table->json('valores_ad');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidade_ldap_configuracoes');
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: `2026_09_06_000005_create_unidade_ldap_configuracoes_table ... DONE`

- [ ] **Step 3: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnidadeLdapConfiguracao extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'unidade_ldap_configuracoes';

    protected $fillable = [
        'unidade_id', 'host', 'port', 'base_dn', 'username', 'password',
        'use_ssl', 'use_starttls', 'unidade_attribute', 'valores_ad', 'ativo',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'encrypted',
        'valores_ad' => 'array',
        'use_ssl' => 'boolean',
        'use_starttls' => 'boolean',
        'ativo' => 'boolean',
        'port' => 'integer',
    ];

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function paraConexaoLdap(): array
    {
        return [
            'hosts' => [$this->host],
            'port' => $this->port,
            'base_dn' => $this->base_dn,
            'username' => $this->username,
            'password' => $this->password,
            'use_ssl' => $this->use_ssl,
            'use_tls' => $this->use_starttls,
            'timeout' => 5,
            'options' => [
                LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_NEVER,
            ],
        ];
    }
}
```

- [ ] **Step 4: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Unidade;
use App\Models\UnidadeLdapConfiguracao;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnidadeLdapConfiguracaoFactory extends Factory
{
    protected $model = UnidadeLdapConfiguracao::class;

    public function definition(): array
    {
        return [
            'unidade_id' => Unidade::factory(),
            'host' => fake()->domainName(),
            'port' => 636,
            'base_dn' => 'DC=' . fake()->domainWord() . ',DC=local',
            'username' => 'svc-ldap@' . fake()->domainName(),
            'password' => 'senha-service-account',
            'use_ssl' => true,
            'use_starttls' => false,
            'unidade_attribute' => 'department',
            'valores_ad' => [fake()->word()],
            'ativo' => true,
        ];
    }
}
```

- [ ] **Step 5: Write the failing test**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\UnidadeLdapConfiguracao;
use Tests\TestCase;

class UnidadeLdapConfiguracaoTest extends TestCase
{
    public function test_password_e_criptografada_e_oculta_na_serializacao(): void
    {
        $config = UnidadeLdapConfiguracao::factory()->create(['password' => 'senha-em-claro']);

        $bruto = \DB::table('unidade_ldap_configuracoes')->find($config->id);
        $this->assertNotEquals('senha-em-claro', $bruto->password);

        $config->refresh();
        $this->assertEquals('senha-em-claro', $config->password);
        $this->assertArrayNotHasKey('password', $config->toArray());
    }

    public function test_para_conexao_ldap_monta_array_de_conexao_ldaprecord(): void
    {
        $config = UnidadeLdapConfiguracao::factory()->make([
            'host' => '10.0.0.5',
            'port' => 636,
            'base_dn' => 'DC=empresa,DC=local',
            'username' => 'svc@empresa.local',
            'password' => 'segredo',
            'use_ssl' => true,
            'use_starttls' => false,
        ]);

        $conexao = $config->paraConexaoLdap();

        $this->assertSame(['10.0.0.5'], $conexao['hosts']);
        $this->assertSame(636, $conexao['port']);
        $this->assertSame('DC=empresa,DC=local', $conexao['base_dn']);
        $this->assertSame('svc@empresa.local', $conexao['username']);
        $this->assertSame('segredo', $conexao['password']);
        $this->assertTrue($conexao['use_ssl']);
        $this->assertFalse($conexao['use_tls']);
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/UnidadeLdapConfiguracaoTest.php`
Expected: PASS (2 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_06_000005_create_unidade_ldap_configuracoes_table.php app/Models/UnidadeLdapConfiguracao.php database/factories/UnidadeLdapConfiguracaoFactory.php tests/Unit/Models/UnidadeLdapConfiguracaoTest.php
git commit -m "feat: adiciona modelo e tabela de configuracao LDAP por unidade"
```

---

### Task 2: Data migration from `.env` / `unidade_ad_mapeamentos` into `unidade_ldap_configuracoes`

**Files:**
- Create: `database/migrations/2026_09_06_000006_migrate_dados_ldap_para_unidade_ldap_configuracoes.php`
- Test: `tests/Feature/Migrations/MigrarDadosLdapTest.php`

**Interfaces:**
- Consumes: `App\Models\UnidadeAdMapeamento` (existing, table `unidade_ad_mapeamentos`), `App\Models\UnidadeLdapConfiguracao` (Task 1), `config('ldap.connections.default')` (existing `config/ldap.php`).
- Produces: for every distinct `unidade_id` present in `unidade_ad_mapeamentos`, one row in `unidade_ldap_configuracoes` with `valores_ad` = array of that unit's `valor_ad` values and connection fields copied from `config('ldap.connections.default')`.

- [ ] **Step 1: Write the migration**

```php
<?php

use App\Models\Unidade;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
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
```

Add `use Illuminate\Database\Schema\Schema;` import at the top alongside the others (needed for `Schema::hasTable`).

- [ ] **Step 2: Write the failing test**

```php
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
```

Note: since `RefreshDatabase`/migrations already run all migrations for the test DB before this test executes, this test re-runs the single migration file explicitly to exercise it in isolation — the `updateOrInsert` in Step 1 makes that safe to call twice.

- [ ] **Step 3: Run test to verify it fails first, without the migration file present**

Run: `php artisan test tests/Feature/Migrations/MigrarDadosLdapTest.php`
Expected: FAIL (`unidade_ldap_configuracoes` empty, or migration path not found) before Step 1's file exists — confirm this by running the test before creating the file, then create the file and re-run.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan migrate` then `php artisan test tests/Feature/Migrations/MigrarDadosLdapTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_06_000006_migrate_dados_ldap_para_unidade_ldap_configuracoes.php tests/Feature/Migrations/MigrarDadosLdapTest.php
git commit -m "feat: migra dados de LDAP do .env e mapeamentos existentes para configuracao por unidade"
```

---

### Task 3: Admin CRUD endpoints for `UnidadeLdapConfiguracao`

**Files:**
- Create: `app/Http/Requests/UnidadeLdapConfig/UpsertUnidadeLdapConfigRequest.php`
- Create: `app/Http/Controllers/Api/UnidadeLdapConfigController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Unidade/UnidadeLdapConfigApiTest.php`

**Interfaces:**
- Consumes: `App\Models\UnidadeLdapConfiguracao` (Task 1), `App\Models\Unidade` (existing).
- Produces: routes `GET/PUT/DELETE /api/unidades/{unidade}/ldap-config`, admin-only, JSON responses `{ id, unidade_id, host, port, base_dn, username, use_ssl, use_starttls, unidade_attribute, valores_ad, ativo, senha_configurada: bool }` (no `password` key ever).

- [ ] **Step 1: Write the FormRequest**

```php
<?php

namespace App\Http\Requests\UnidadeLdapConfig;

use Illuminate\Foundation\Http\FormRequest;

class UpsertUnidadeLdapConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'base_dn' => 'required|string|max:500',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'use_ssl' => 'boolean',
            'use_starttls' => 'boolean',
            'unidade_attribute' => 'required|string|max:100',
            'valores_ad' => 'required|array|min:1',
            'valores_ad.*' => 'required|string|max:255',
            'ativo' => 'boolean',
        ];
    }
}
```

- [ ] **Step 2: Write the controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnidadeLdapConfig\UpsertUnidadeLdapConfigRequest;
use App\Models\Unidade;
use App\Models\UnidadeLdapConfiguracao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnidadeLdapConfigController extends Controller
{
    public function show(Unidade $unidade): JsonResponse
    {
        $config = UnidadeLdapConfiguracao::where('unidade_id', $unidade->id)->first();

        if (! $config) {
            return response()->json(['message' => 'Unidade sem configuração LDAP.'], 404);
        }

        return response()->json($this->apresentar($config));
    }

    public function upsert(UpsertUnidadeLdapConfigRequest $request, Unidade $unidade): JsonResponse
    {
        $data = $request->validated();

        $config = UnidadeLdapConfiguracao::where('unidade_id', $unidade->id)->first();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);

            if (! $config) {
                return response()->json(['errors' => ['password' => ['A senha é obrigatória ao criar uma nova configuração.']]], 422);
            }
        }

        $data['unidade_id'] = $unidade->id;
        $data['use_ssl'] = $data['use_ssl'] ?? true;
        $data['use_starttls'] = $data['use_starttls'] ?? false;
        $data['ativo'] = $data['ativo'] ?? true;

        $config = $config
            ? tap($config)->update($data)
            : UnidadeLdapConfiguracao::create($data);

        return response()->json($this->apresentar($config));
    }

    public function destroy(Unidade $unidade): JsonResponse
    {
        UnidadeLdapConfiguracao::where('unidade_id', $unidade->id)->delete();

        return response()->json(['message' => 'Configuração LDAP removida.']);
    }

    private function apresentar(UnidadeLdapConfiguracao $config): array
    {
        return [
            'id' => $config->id,
            'unidade_id' => $config->unidade_id,
            'host' => $config->host,
            'port' => $config->port,
            'base_dn' => $config->base_dn,
            'username' => $config->username,
            'use_ssl' => $config->use_ssl,
            'use_starttls' => $config->use_starttls,
            'unidade_attribute' => $config->unidade_attribute,
            'valores_ad' => $config->valores_ad,
            'ativo' => $config->ativo,
            'senha_configurada' => true,
        ];
    }
}
```

- [ ] **Step 3: Register routes**

In `routes/api.php`, inside the existing `Route::middleware('admin')->group(...)` block that wraps the `usuarios` resource (around line 132-134), add before the closing `});`:

```php
    Route::get('unidades/{unidade}/ldap-config', [UnidadeLdapConfigController::class, 'show']);
    Route::put('unidades/{unidade}/ldap-config', [UnidadeLdapConfigController::class, 'upsert']);
    Route::delete('unidades/{unidade}/ldap-config', [UnidadeLdapConfigController::class, 'destroy']);
```

Add `use App\Http\Controllers\Api\UnidadeLdapConfigController;` to the file's `use` block at the top.

- [ ] **Step 4: Write the failing tests**

```php
<?php

namespace Tests\Feature\Unidade;

use App\Models\Unidade;
use App\Models\Usuario;
use Tests\TestCase;

class UnidadeLdapConfigApiTest extends TestCase
{
    private function admin(): Usuario
    {
        return Usuario::factory()->create(['perfil' => 'admin']);
    }

    public function test_nao_admin_recebe_403(): void
    {
        $unidade = Unidade::factory()->create();
        $gestor = Usuario::factory()->create(['perfil' => 'gestor']);

        $this->actingAs($gestor, 'sanctum')
            ->getJson("/api/unidades/{$unidade->id}/ldap-config")
            ->assertForbidden();
    }

    public function test_show_sem_configuracao_retorna_404(): void
    {
        $unidade = Unidade::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/unidades/{$unidade->id}/ldap-config")
            ->assertNotFound();
    }

    public function test_upsert_cria_configuracao_e_nunca_retorna_senha(): void
    {
        $unidade = Unidade::factory()->create();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/unidades/{$unidade->id}/ldap-config", [
                'host' => '10.0.0.5',
                'port' => 636,
                'base_dn' => 'DC=empresa,DC=local',
                'username' => 'svc@empresa.local',
                'password' => 'segredo123',
                'use_ssl' => true,
                'use_starttls' => false,
                'unidade_attribute' => 'department',
                'valores_ad' => ['HOSP-CENTRO'],
                'ativo' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('host', '10.0.0.5')
            ->assertJsonPath('senha_configurada', true)
            ->assertJsonMissingPath('password');

        $this->assertDatabaseHas('unidade_ldap_configuracoes', [
            'unidade_id' => $unidade->id,
            'host' => '10.0.0.5',
        ]);
    }

    public function test_criar_sem_senha_retorna_422(): void
    {
        $unidade = Unidade::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/unidades/{$unidade->id}/ldap-config", [
                'host' => '10.0.0.5',
                'port' => 636,
                'base_dn' => 'DC=empresa,DC=local',
                'username' => 'svc@empresa.local',
                'unidade_attribute' => 'department',
                'valores_ad' => ['HOSP-CENTRO'],
            ])
            ->assertStatus(422);
    }

    public function test_atualizar_com_senha_em_branco_mantem_senha_anterior(): void
    {
        $unidade = Unidade::factory()->create();
        $config = \App\Models\UnidadeLdapConfiguracao::factory()->create([
            'unidade_id' => $unidade->id,
            'password' => 'senha-original',
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/unidades/{$unidade->id}/ldap-config", [
                'host' => 'novo-host',
                'port' => $config->port,
                'base_dn' => $config->base_dn,
                'username' => $config->username,
                'unidade_attribute' => $config->unidade_attribute,
                'valores_ad' => $config->valores_ad,
            ])
            ->assertOk()
            ->assertJsonPath('host', 'novo-host');

        $this->assertEquals('senha-original', $config->fresh()->password);
    }

    public function test_valores_ad_vazio_falha_validacao(): void
    {
        $unidade = Unidade::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/unidades/{$unidade->id}/ldap-config", [
                'host' => '10.0.0.5',
                'port' => 636,
                'base_dn' => 'DC=empresa,DC=local',
                'username' => 'svc@empresa.local',
                'password' => 'segredo',
                'unidade_attribute' => 'department',
                'valores_ad' => [],
            ])
            ->assertStatus(422);
    }

    public function test_destroy_remove_configuracao(): void
    {
        $unidade = Unidade::factory()->create();
        \App\Models\UnidadeLdapConfiguracao::factory()->create(['unidade_id' => $unidade->id]);

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/unidades/{$unidade->id}/ldap-config")
            ->assertOk();

        $this->assertDatabaseMissing('unidade_ldap_configuracoes', ['unidade_id' => $unidade->id]);
    }
}
```

- [ ] **Step 5: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Unidade/UnidadeLdapConfigApiTest.php`
Expected: FAIL (route/controller not found) before Steps 1-3; after creating the files, re-run.

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Unidade/UnidadeLdapConfigApiTest.php`
Expected: PASS (7 tests)

- [ ] **Step 7: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS, no regressions

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/UnidadeLdapConfig app/Http/Controllers/Api/UnidadeLdapConfigController.php routes/api.php tests/Feature/Unidade/UnidadeLdapConfigApiTest.php
git commit -m "feat: adiciona CRUD admin de configuracao LDAP por unidade"
```

---

### Task 4: "Test connection" endpoint

**Files:**
- Modify: `app/Http/Controllers/Api/UnidadeLdapConfigController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Unidade/TestarConexaoLdapTest.php`

**Interfaces:**
- Consumes: `UnidadeLdapConfiguracao::paraConexaoLdap()` shape (Task 1) — built ad hoc from request input, not persisted.
- Produces: `POST /api/unidades/{unidade}/ldap-config/testar` → `{ sucesso: bool, mensagem: string }`.

- [ ] **Step 1: Add the `testar` method to the controller**

```php
    public function testar(Request $request, Unidade $unidade): JsonResponse
    {
        $data = $request->validate([
            'host' => 'required|string',
            'port' => 'required|integer',
            'base_dn' => 'required|string',
            'username' => 'required|string',
            'password' => 'nullable|string',
        ]);

        if (blank($data['password'] ?? null)) {
            $configExistente = UnidadeLdapConfiguracao::where('unidade_id', $unidade->id)->first();
            if (! $configExistente) {
                return response()->json(['sucesso' => false, 'mensagem' => 'Informe a senha da conta de serviço para testar.']);
            }
            $data['password'] = $configExistente->password;
        }

        $conexao = new \LdapRecord\Connection([
            'hosts' => [$data['host']],
            'port' => $data['port'],
            'base_dn' => $data['base_dn'],
            'username' => $data['username'],
            'password' => $data['password'],
            'use_ssl' => $request->boolean('use_ssl', true),
            'use_tls' => $request->boolean('use_starttls', false),
            'timeout' => 5,
            'options' => [
                LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_NEVER,
            ],
        ]);

        try {
            $conexao->connect();
        } catch (\LdapRecord\LdapRecordException $e) {
            return response()->json([
                'sucesso' => false,
                'mensagem' => 'Falha ao autenticar a conta de serviço: ' . $e->getMessage(),
            ]);
        }

        $resultado = $conexao->query()->in($data['base_dn'])->rawFilter('(objectClass=user)')->limit(1)->get();

        if (empty($resultado)) {
            return response()->json([
                'sucesso' => true,
                'mensagem' => 'Conexão OK, mas nenhum usuário encontrado no Base DN informado — confira o Base DN.',
            ]);
        }

        return response()->json(['sucesso' => true, 'mensagem' => 'Conexão bem-sucedida.']);
    }
```

- [ ] **Step 2: Register the route**

In `routes/api.php`, add to the same admin group as Task 3:

```php
    Route::post('unidades/{unidade}/ldap-config/testar', [UnidadeLdapConfigController::class, 'testar']);
```

- [ ] **Step 3: Write the failing tests**

```php
<?php

namespace Tests\Feature\Unidade;

use App\Models\Unidade;
use App\Models\Usuario;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use Tests\TestCase;

class TestarConexaoLdapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DirectoryEmulator::setup('default');
    }

    protected function tearDown(): void
    {
        DirectoryEmulator::tearDown();
        parent::tearDown();
    }

    public function test_testar_conexao_nao_persiste_nada(): void
    {
        $unidade = Unidade::factory()->create();
        $admin = Usuario::factory()->create(['perfil' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/unidades/{$unidade->id}/ldap-config/testar", [
                'host' => 'dc-inexistente.invalido',
                'port' => 636,
                'base_dn' => 'DC=empresa,DC=local',
                'username' => 'svc@empresa.local',
                'password' => 'qualquer',
            ])
            ->assertOk()
            ->assertJsonPath('sucesso', false);

        $this->assertDatabaseMissing('unidade_ldap_configuracoes', ['unidade_id' => $unidade->id]);
    }

    public function test_testar_conexao_exige_admin(): void
    {
        $unidade = Unidade::factory()->create();
        $operador = Usuario::factory()->create(['perfil' => 'operador']);

        $this->actingAs($operador, 'sanctum')
            ->postJson("/api/unidades/{$unidade->id}/ldap-config/testar", [
                'host' => 'x', 'port' => 636, 'base_dn' => 'x', 'username' => 'x', 'password' => 'x',
            ])
            ->assertForbidden();
    }
}
```

Note: `DirectoryEmulator` fakes LdapRecord's `default` connection, but `testar()` builds an ad hoc, unnamed `Connection` — against a bogus host, real DNS/connect will fail fast and naturally exercise the failure branch, which is what `test_testar_conexao_nao_persiste_nada` checks. No emulator wiring is needed for that specific test; `setUp`/`tearDown` are kept for consistency with the rest of the LDAP test suite and to avoid leaking a real `default` connection registration across tests.

- [ ] **Step 4: Run tests to verify they fail, then pass**

Run: `php artisan test tests/Feature/Unidade/TestarConexaoLdapTest.php`
Expected: FAIL before Steps 1-2 exist, PASS after.

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS, no regressions

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/UnidadeLdapConfigController.php routes/api.php tests/Feature/Unidade/TestarConexaoLdapTest.php
git commit -m "feat: adiciona endpoint de teste de conexao LDAP"
```

---

### Task 5: Rewrite `AuthController@loginAd` to try every active unit

**Files:**
- Modify: `app/Http/Controllers/Api/AuthController.php`
- Modify: `tests/Feature/Auth/LoginAdTest.php` (full rewrite)

**Interfaces:**
- Consumes: `UnidadeLdapConfiguracao::paraConexaoLdap()` (Task 1), `LdapRecord\Container`, `LdapRecord\Connection`, `LdapRecord\Models\ActiveDirectory\User as LdapUser`.
- Produces: private method `AuthController::resolverLoginLdap(string $usuario, string $senha): ?array` returning `['ldapUser' => LdapUser, 'unidadeConfig' => UnidadeLdapConfiguracao]` or `null`. `loginAd()` public signature unchanged.

- [ ] **Step 1: Replace `loginAd` and add the resolver method**

In `app/Http/Controllers/Api/AuthController.php`:

1. Update the `use` block: remove `use App\Models\UnidadeAdMapeamento;`, add `use App\Models\UnidadeLdapConfiguracao;` and `use LdapRecord\Container;` and `use LdapRecord\Connection;`.
2. Replace the entire `loginAd` method body with:

```php
    public function loginAd(LoginAdRequest $r)
    {
        $input = $r->validated();

        $resolvido = $this->resolverLoginLdap($input['usuario'], $input['senha']);

        if ($resolvido === null) {
            return response()->json(['error' => 'Usuário ou senha inválidos'], 401);
        }

        if ($resolvido === []) {
            return response()->json(['error' => 'Serviço de autenticação indisponível, tente novamente'], 503);
        }

        ['ldapUser' => $ldapUser, 'unidadeConfig' => $unidadeConfig] = $resolvido;

        $guid = $ldapUser->getConvertedGuid();

        if (blank($guid)) {
            Log::error('AD retornou usuário sem objectGUID utilizável', ['dn' => $ldapUser->getDn()]);

            return response()->json(['error' => 'Serviço de autenticação indisponível, tente novamente'], 503);
        }

        $mail = $ldapUser->getFirstAttribute('mail');
        $unidadeId = $unidadeConfig->unidade_id;

        $usuario = Usuario::where('ldap_guid', $guid)->first();

        $vinculandoContaExistente = false;
        if (! $usuario && $mail) {
            $usuario = Usuario::whereNull('ldap_guid')->where('email', $mail)->first();
            $vinculandoContaExistente = (bool) $usuario;
        }

        $novoRegistro = ! $usuario;
        $usuario = $usuario ?: new Usuario;

        if ($novoRegistro) {
            $usuario->fill([
                'nome' => $ldapUser->getFirstAttribute('displayname'),
                'email' => $mail,
                'perfil' => 'solicitante',
                'unidade_id' => $unidadeId,
                'ativo' => true,
            ]);
        } elseif (! $vinculandoContaExistente) {
            $usuario->fill([
                'nome' => $ldapUser->getFirstAttribute('displayname'),
                'email' => $mail,
                'unidade_id' => $unidadeId,
            ]);
        }

        $usuario->ldap_guid = $guid;
        $usuario->ldap_sync_at = now();
        $usuario->save();

        if ($usuario->exists && ! $usuario->ativo) {
            return response()->json(['error' => 'Usuário ou senha inválidos'], 401);
        }

        $usuario->update(['ultimo_acesso' => now()]);
        $token = $usuario->createToken('app', [], now()->addHours(8))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->buildUserPayload($usuario),
        ]);
    }

    /**
     * Tenta autenticar usuario/senha contra cada UnidadeLdapConfiguracao
     * ativa, em ordem, até um bind funcionar.
     *
     * @return array{ldapUser: LdapUser, unidadeConfig: UnidadeLdapConfiguracao}|null|array{}
     *   null       => nenhuma unidade autenticou (usuário/senha inválidos)
     *   []         => nenhuma unidade respondeu (falha de conectividade em todas)
     *   array{...} => sucesso
     */
    private function resolverLoginLdap(string $usuario, string $senha): ?array
    {
        $configs = UnidadeLdapConfiguracao::where('ativo', true)->get();
        $algumaRespondeu = false;

        foreach ($configs as $config) {
            $nomeConexao = 'unidade-ldap-' . $config->id;

            try {
                Container::getInstance()->addConnection(
                    new Connection($config->paraConexaoLdap()),
                    $nomeConexao
                );

                $ldapUser = LdapUser::on($nomeConexao)->findBy('samaccountname', $usuario);

                if (! $ldapUser) {
                    $algumaRespondeu = true;
                    continue;
                }

                $binded = $ldapUser->getConnection()->auth()->attempt($ldapUser->getDn(), $senha);
                $algumaRespondeu = true;

                if ($binded) {
                    return ['ldapUser' => $ldapUser, 'unidadeConfig' => $config];
                }
            } catch (LdapRecordException $e) {
                Log::warning('Falha ao consultar AD de uma unidade durante login', [
                    'unidade_id' => $config->unidade_id,
                    'erro' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return $algumaRespondeu ? null : [];
    }
```

3. Delete the now-unused `use LdapRecord\LdapRecordException;`? No — keep it, it's still used in `resolverLoginLdap`'s catch clause. Confirm the `use` statement for `LdapRecordException` remains present.

- [ ] **Step 2: Rewrite `LoginAdTest`**

Replace the full contents of `tests/Feature/Auth/LoginAdTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\Unidade;
use App\Models\UnidadeLdapConfiguracao;
use App\Models\Usuario;
use Illuminate\Support\Str;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use Tests\TestCase;

class LoginAdTest extends TestCase
{
    private function criarUnidadeComLdap(string $nomeConexaoEmulada, array $overrides = []): array
    {
        $unidade = Unidade::factory()->create();
        $config = UnidadeLdapConfiguracao::factory()->create(array_merge([
            'unidade_id' => $unidade->id,
        ], $overrides));

        // A config real usaria host/porta reais; para os testes, o
        // AuthController::resolverLoginLdap registra uma conexão nomeada
        // 'unidade-ldap-{id}' por config — o DirectoryEmulator intercepta
        // qualquer conexão LdapRecord registrada, então basta garantir que
        // o emulador tenha sido configurado para esse nome antes do login.
        DirectoryEmulator::setup('unidade-ldap-' . $config->id);

        return [$unidade, $config];
    }

    protected function tearDown(): void
    {
        DirectoryEmulator::tearDown();
        parent::tearDown();
    }

    private function criarUsuarioLdap(string $fake, array $attrs = [], bool $autorizarBind = true): LdapUser
    {
        $emulador = DirectoryEmulator::getConnectionFake($fake) ?? DirectoryEmulator::setup($fake);

        $user = LdapUser::on($fake)->create(array_merge([
            'cn' => 'João Silva',
            'displayname' => 'João Silva',
            'samaccountname' => 'jsilva',
            'mail' => 'jsilva@empresa.com.br',
            'objectguid' => Str::uuid()->toString(),
        ], $attrs));

        if ($autorizarBind) {
            $emulador->actingAs($user);
        }

        return $user;
    }

    public function test_login_ad_com_credenciais_validas_na_unica_unidade_cria_usuario_solicitante(): void
    {
        [$unidade, $config] = $this->criarUnidadeComLdap('u1');
        $this->criarUsuarioLdap('unidade-ldap-' . $config->id);

        $response = $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-correta',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'nome', 'email', 'perfil']])
            ->assertJsonPath('user.perfil', 'solicitante')
            ->assertJsonPath('user.unidade_id', $unidade->id);

        $this->assertDatabaseHas('usuarios', [
            'email' => 'jsilva@empresa.com.br',
            'perfil' => 'solicitante',
        ]);
    }

    public function test_login_ad_tenta_proxima_unidade_quando_primeira_nao_tem_o_usuario(): void
    {
        [, $configA] = $this->criarUnidadeComLdap('a');
        [$unidadeB, $configB] = $this->criarUnidadeComLdap('b');

        // Unidade A não tem esse usuário no seu diretório; unidade B tem.
        $this->criarUsuarioLdap('unidade-ldap-' . $configB->id);

        $response = $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-correta',
        ]);

        $response->assertOk()->assertJsonPath('user.unidade_id', $unidadeB->id);
    }

    public function test_login_ad_ignora_unidade_com_configuracao_inativa(): void
    {
        [$unidadeInativa, $configInativa] = $this->criarUnidadeComLdap('inativa', ['ativo' => false]);
        $this->criarUsuarioLdap('unidade-ldap-' . $configInativa->id);

        $response = $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-correta',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_ad_com_senha_incorreta_em_todas_unidades_retorna_401(): void
    {
        [, $config] = $this->criarUnidadeComLdap('u1');
        $this->criarUsuarioLdap('unidade-ldap-' . $config->id, [], autorizarBind: false);

        $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-errada',
        ])->assertUnauthorized()
            ->assertJson(['error' => 'Usuário ou senha inválidos']);
    }

    public function test_login_ad_sem_nenhuma_unidade_configurada_retorna_401(): void
    {
        $this->postJson('/api/auth/login-ad', [
            'usuario' => 'naoexiste',
            'senha' => 'qualquer',
        ])->assertUnauthorized()
            ->assertJson(['error' => 'Usuário ou senha inválidos']);
    }

    public function test_login_ad_com_usuario_existente_atualiza_dados(): void
    {
        [, $config] = $this->criarUnidadeComLdap('u1');
        $ldapUser = $this->criarUsuarioLdap('unidade-ldap-' . $config->id);
        $guid = $ldapUser->getConvertedGuid();

        $usuario = Usuario::factory()->create([
            'ldap_guid' => $guid,
            'nome' => 'Nome Antigo',
            'perfil' => 'solicitante',
        ]);

        $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-correta',
        ])->assertOk();

        $this->assertDatabaseHas('usuarios', [
            'id' => $usuario->id,
            'nome' => 'João Silva',
        ]);
    }

    public function test_login_ad_com_guid_ausente_nao_cria_nem_altera_usuario(): void
    {
        [, $config] = $this->criarUnidadeComLdap('u1');
        $countAntes = Usuario::count();

        $this->criarUsuarioLdap('unidade-ldap-' . $config->id);

        $ldapObject = \LdapRecord\Laravel\Testing\LdapObject::query()->firstOrFail();
        $ldapObject->update(['guid' => '']);
        $ldapObject->attributes()->where('name', 'objectguid')->delete();

        $response = $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-correta',
        ]);

        $response->assertStatus(503);
        $this->assertDatabaseCount('usuarios', $countAntes);
    }

    public function test_login_ad_com_email_de_conta_existente_vincula_sem_alterar_perfil(): void
    {
        $unidadeAntiga = Unidade::factory()->create();
        $admin = Usuario::factory()->create([
            'nome' => 'Fernando Admin',
            'email' => 'jsilva@empresa.com.br',
            'perfil' => 'admin',
            'unidade_id' => $unidadeAntiga->id,
            'ldap_guid' => null,
        ]);

        [, $config] = $this->criarUnidadeComLdap('u1');
        $ldapUser = $this->criarUsuarioLdap('unidade-ldap-' . $config->id);

        $response = $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-correta',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $admin->id)
            ->assertJsonPath('user.perfil', 'admin')
            ->assertJsonPath('user.unidade_id', $unidadeAntiga->id);

        $this->assertDatabaseHas('usuarios', [
            'id' => $admin->id,
            'nome' => 'Fernando Admin',
            'perfil' => 'admin',
            'unidade_id' => $unidadeAntiga->id,
            'ldap_guid' => $ldapUser->getConvertedGuid(),
        ]);

        $this->assertDatabaseCount('usuarios', 1);
    }

    public function test_login_ad_com_usuario_inativo_retorna_401_e_nao_reativa(): void
    {
        [, $config] = $this->criarUnidadeComLdap('u1');
        $ldapUser = $this->criarUsuarioLdap('unidade-ldap-' . $config->id);
        $guid = $ldapUser->getConvertedGuid();

        $usuario = Usuario::factory()->create([
            'ldap_guid' => $guid,
            'nome' => 'Nome Antigo',
            'perfil' => 'solicitante',
            'ativo' => false,
        ]);

        $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-correta',
        ])->assertStatus(401)
            ->assertJson(['error' => 'Usuário ou senha inválidos']);

        $this->assertDatabaseHas('usuarios', [
            'id' => $usuario->id,
            'ativo' => false,
        ]);
    }
}
```

- [ ] **Step 3: Run the rewritten test file**

Run: `php artisan test tests/Feature/Auth/LoginAdTest.php`
Expected: PASS (9 tests). If `DirectoryEmulator::setup()`/`getConnectionFake()` behave differently than assumed for multiple named connections in this LdapRecord version, adjust `criarUnidadeComLdap`/`criarUsuarioLdap` to match the installed `directorytree/ldaprecord-laravel` ^4.0 testing API (check `vendor/directorytree/ldaprecord-laravel/src/Testing/DirectoryEmulator.php` for the exact multi-connection setup signature) — the intent (one fake directory per unit's connection name) must be preserved even if the exact static-call shape needs a tweak.

- [ ] **Step 4: Run the full suite**

Run: `php artisan test`
Expected: PASS, no regressions

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/AuthController.php tests/Feature/Auth/LoginAdTest.php
git commit -m "feat: login-ad tenta cada unidade LDAP ativa ate autenticar"
```

---

### Task 6: Remove legacy single-connection LDAP config

**Files:**
- Create: `database/migrations/2026_09_06_000007_drop_unidade_ad_mapeamentos_table.php`
- Remove: `app/Models/UnidadeAdMapeamento.php`
- Remove: `config/ldap.php`
- Modify: `.env.example` (remove `LDAP_*` keys)
- Modify: `tests/Unit/Models/UnidadeAdMapeamentoTest.php` — remove (model no longer exists)

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new — this is a cleanup task confirming no remaining references.

- [ ] **Step 1: Search for remaining references before deleting anything**

Run: `grep -rn "UnidadeAdMapeamento\|config('ldap\|config(\"ldap" app resources routes tests database --include=*.php`
Expected: only the files this task will delete/edit should appear. If `AuthController.php` still references `UnidadeAdMapeamento`, stop — Task 5 was not fully applied; fix it first.

- [ ] **Step 2: Write the drop migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('unidade_ad_mapeamentos');
    }

    public function down(): void
    {
        Schema::create('unidade_ad_mapeamentos', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('valor_ad')->unique();
            $table->foreignUuid('unidade_id')->constrained('unidades');
            $table->timestamps();
        });
    }
};
```

- [ ] **Step 3: Delete the model, its test, and `config/ldap.php`**

```bash
git rm app/Models/UnidadeAdMapeamento.php
git rm tests/Unit/Models/UnidadeAdMapeamentoTest.php
git rm config/ldap.php
```

(If `UnidadeAdMapeamentoTest.php` does not exist at that exact path, locate it first with `Glob` for `**/UnidadeAdMapeamento*Test.php` and remove the actual path.)

- [ ] **Step 4: Remove `LDAP_*` keys from `.env.example`**

Open `.env.example`, delete every line starting with `LDAP_`.

- [ ] **Step 5: Run migration and full suite**

Run: `php artisan migrate && php artisan test`
Expected: `2026_09_06_000007_drop_unidade_ad_mapeamentos_table ... DONE`; full suite PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "chore: remove configuracao LDAP legada de conexao unica"
```

---

### Task 7: Frontend API client for LDAP config

**Files:**
- Create: `resources/js/api/unidadeLdapConfig.js`

**Interfaces:**
- Produces: `buscar(unidadeId)`, `salvar(unidadeId, data)`, `remover(unidadeId)`, `testar(unidadeId, data)` — each returning the underlying axios promise, matching the pattern in `resources/js/api/unidades.js`.

- [ ] **Step 1: Write the API client**

```js
import api from './axios'

export const buscar = (unidadeId) => api.get(`/unidades/${unidadeId}/ldap-config`)
export const salvar = (unidadeId, data) => api.put(`/unidades/${unidadeId}/ldap-config`, data)
export const remover = (unidadeId) => api.delete(`/unidades/${unidadeId}/ldap-config`)
export const testar = (unidadeId, data) => api.post(`/unidades/${unidadeId}/ldap-config/testar`, data)
```

- [ ] **Step 2: Verify the frontend build still compiles**

Run: `npm run build`
Expected: build succeeds (this file has no consumers yet, so it just needs valid syntax).

- [ ] **Step 3: Commit**

```bash
git add resources/js/api/unidadeLdapConfig.js
git commit -m "feat: adiciona cliente de API para configuracao LDAP por unidade"
```

---

### Task 8: `ConfiguracoesHub` page, routes, and sidebar entry

**Files:**
- Create: `resources/js/pages/configuracoes/ConfiguracoesHub.jsx`
- Modify: `resources/js/FleetApp.jsx`
- Modify: `resources/js/components/layout/Sidebar.jsx`
- Modify: `resources/js/components/layout/Layout.jsx`

**Interfaces:**
- Produces: route `/configuracoes` rendering `ConfiguracoesHub`, which links to `/configuracoes/ldap` (wired fully in Task 9).

- [ ] **Step 1: Write `ConfiguracoesHub.jsx`**

```jsx
import { useNavigate } from 'react-router-dom'
import { ShieldCheck, ChevronRight } from 'lucide-react'

const secoes = [
  {
    to: '/configuracoes/ldap',
    titulo: 'LDAP por Unidade',
    descricao: 'Configure a conexão com o Active Directory de cada unidade para permitir login de solicitantes via rede.',
    icon: ShieldCheck,
  },
]

export default function ConfiguracoesHub() {
  const navigate = useNavigate()

  return (
    <div className="space-y-3">
      {secoes.map(({ to, titulo, descricao, icon: Icon }) => (
        <div
          key={to}
          onClick={() => navigate(to)}
          className="flex items-center justify-between bg-white rounded-xl border border-gray-200 px-5 py-4 cursor-pointer hover:bg-gray-50 transition-colors"
        >
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-lg bg-brand-50 flex items-center justify-center">
              <Icon size={18} className="text-brand-600" />
            </div>
            <div>
              <p className="text-sm font-medium text-gray-800">{titulo}</p>
              <p className="text-xs text-gray-400 mt-0.5">{descricao}</p>
            </div>
          </div>
          <ChevronRight size={16} className="text-gray-300 shrink-0" />
        </div>
      ))}
    </div>
  )
}
```

- [ ] **Step 2: Add routes in `FleetApp.jsx`**

Add import near the other page imports:
```jsx
import ConfiguracoesHub from './pages/configuracoes/ConfiguracoesHub'
import ConfiguracoesLdap from './pages/configuracoes/ConfiguracoesLdap'
```

Add routes inside the `<Route element={<PrivateRoute />}>` block, after the `/unidades/:id` route:
```jsx
              <Route path="/configuracoes" element={
                <AdminRoute><ConfiguracoesHub /></AdminRoute>
              } />
              <Route path="/configuracoes/ldap" element={
                <AdminRoute><ConfiguracoesLdap /></AdminRoute>
              } />
```

(`ConfiguracoesLdap` is created in Task 9 — this task's build step will fail to compile until that file exists, so Steps 2 and the Task 9 file creation must land together; if executing tasks strictly in order, create a placeholder file in this task instead — see Step 3.)

- [ ] **Step 3: Create a placeholder `ConfiguracoesLdap.jsx` so the build compiles**

```jsx
export default function ConfiguracoesLdap() {
  return null
}
```
(Task 9 replaces this file's contents entirely.)

- [ ] **Step 4: Add the sidebar entry**

In `resources/js/components/layout/Sidebar.jsx`:
1. Add `Settings` to the `lucide-react` import list (line 4).
2. In the ADMIN block's array (around line 142-145), add after the `/unidades` entry:
```jsx
                  { to: '/configuracoes', label: 'Configurações', icon: Settings },
```

- [ ] **Step 5: Add the page title**

In `resources/js/components/layout/Layout.jsx`, add to the `pageTitles` object (around line 12-24):
```jsx
  '/configuracoes': 'Configurações',
```

- [ ] **Step 6: Verify the build and manually smoke-test navigation**

Run: `npm run build`
Expected: build succeeds.

Then run `composer dev` (or `php artisan serve` + `npm run dev` in another terminal), log in as an admin user, click "Configurações" in the sidebar, and confirm the hub page renders with the "LDAP por Unidade" card, and clicking it navigates to `/configuracoes/ldap` (blank page is expected until Task 9).

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/configuracoes/ConfiguracoesHub.jsx resources/js/pages/configuracoes/ConfiguracoesLdap.jsx resources/js/FleetApp.jsx resources/js/components/layout/Sidebar.jsx resources/js/components/layout/Layout.jsx
git commit -m "feat: adiciona hub de configuracoes admin com navegacao para LDAP"
```

---

### Task 9: `ConfiguracoesLdap` page with per-unit LDAP form and connection test

**Files:**
- Modify: `resources/js/pages/configuracoes/ConfiguracoesLdap.jsx` (replace placeholder)
- Create: `resources/js/pages/configuracoes/UnidadeLdapConfigForm.jsx`

**Interfaces:**
- Consumes: `resources/js/api/unidades.js` (`listar`), `resources/js/api/unidadeLdapConfig.js` (Task 7: `buscar`, `salvar`, `remover`, `testar`), shared components `Modal`, `Alert`, `LoadingSpinner`.

- [ ] **Step 1: Write `UnidadeLdapConfigForm.jsx`**

```jsx
import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import * as ldapApi from '../../api/unidadeLdapConfig'
import Alert from '../../components/ui/Alert'

export default function UnidadeLdapConfigForm({ unidadeId, config, onSuccess }) {
  const qc = useQueryClient()
  const [form, setForm] = useState({
    host: config?.host ?? '',
    port: config?.port ?? 636,
    base_dn: config?.base_dn ?? '',
    username: config?.username ?? '',
    password: '',
    use_ssl: config?.use_ssl ?? true,
    use_starttls: config?.use_starttls ?? false,
    unidade_attribute: config?.unidade_attribute ?? 'department',
    valoresAdTexto: (config?.valores_ad ?? []).join(', '),
    ativo: config?.ativo ?? true,
  })
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [testeResultado, setTesteResultado] = useState(null)

  const set = (k) => (e) =>
    setForm(f => ({ ...f, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }))

  const montarPayload = () => ({
    host: form.host,
    port: Number(form.port),
    base_dn: form.base_dn,
    username: form.username,
    password: form.password || undefined,
    use_ssl: form.use_ssl,
    use_starttls: form.use_starttls,
    unidade_attribute: form.unidade_attribute,
    valores_ad: form.valoresAdTexto.split(',').map(v => v.trim()).filter(Boolean),
    ativo: form.ativo,
  })

  const testar = useMutation({
    mutationFn: () => ldapApi.testar(unidadeId, montarPayload()),
    onSuccess: (res) => setTesteResultado(res.data),
    onError: (e) => setTesteResultado({ sucesso: false, mensagem: e.response?.data?.message ?? 'Erro ao testar conexão' }),
  })

  const salvar = useMutation({
    mutationFn: () => ldapApi.salvar(unidadeId, montarPayload()),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['unidade-ldap-config', unidadeId] })
      onSuccess()
    },
    onError: (e) => {
      if (e.response?.data?.errors) setFieldErrors(e.response.data.errors)
      else setError(e.response?.data?.message ?? 'Erro ao salvar')
    },
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    salvar.mutate()
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && <Alert type="error" message={error} />}
      {testeResultado && (
        <Alert type={testeResultado.sucesso ? 'success' : 'error'} message={testeResultado.mensagem} />
      )}

      <div className="grid grid-cols-2 gap-4">
        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">Host do Domain Controller *</label>
          <input
            type="text" required value={form.host} onChange={set('host')}
            placeholder="Ex: 10.0.0.5 ou dc.unidade.local"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          {fieldErrors.host && <p className="text-red-500 text-xs mt-1">{fieldErrors.host[0]}</p>}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Porta *</label>
          <input
            type="number" required value={form.port} onChange={set('port')}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div className="flex items-end gap-4 pb-2">
          <label className="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" checked={form.use_ssl} onChange={set('use_ssl')} className="rounded" /> LDAPS (SSL)
          </label>
          <label className="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" checked={form.use_starttls} onChange={set('use_starttls')} className="rounded" /> StartTLS
          </label>
        </div>

        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">Base DN *</label>
          <input
            type="text" required value={form.base_dn} onChange={set('base_dn')}
            placeholder='Ex: OU=Funcionarios,DC=empresa,DC=local'
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          {fieldErrors.base_dn && <p className="text-red-500 text-xs mt-1">{fieldErrors.base_dn[0]}</p>}
        </div>

        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">Usuário da conta de serviço *</label>
          <input
            type="text" required value={form.username} onChange={set('username')}
            placeholder="usuario@dominio.local"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          {fieldErrors.username && <p className="text-red-500 text-xs mt-1">{fieldErrors.username[0]}</p>}
        </div>

        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Senha {config ? '(deixe em branco para manter a atual)' : '*'}
          </label>
          <input
            type="password" required={!config} value={form.password} onChange={set('password')}
            placeholder={config ? '••••••' : ''}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          {fieldErrors.password && <p className="text-red-500 text-xs mt-1">{fieldErrors.password[0]}</p>}
        </div>

        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">Atributo AD de unidade</label>
          <input
            type="text" value={form.unidade_attribute} onChange={set('unidade_attribute')}
            placeholder="department"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">Valores do atributo que identificam esta unidade *</label>
          <input
            type="text" required value={form.valoresAdTexto} onChange={set('valoresAdTexto')}
            placeholder="Ex: HOSP-CENTRO, HOSP-CENTRO-ANEXO"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <p className="text-xs text-gray-400 mt-1">Separe múltiplos valores por vírgula.</p>
          {fieldErrors.valores_ad && <p className="text-red-500 text-xs mt-1">{fieldErrors.valores_ad[0]}</p>}
        </div>

        <div className="col-span-2 flex items-center gap-2">
          <input type="checkbox" id="ativo" checked={form.ativo} onChange={set('ativo')} className="rounded" />
          <label htmlFor="ativo" className="text-sm text-gray-700">Configuração ativa (participa da tentativa de login)</label>
        </div>
      </div>

      <div className="flex justify-between items-center pt-2">
        <button
          type="button"
          onClick={() => testar.mutate()}
          disabled={testar.isPending}
          className="text-sm text-blue-600 hover:text-blue-800 font-medium disabled:opacity-60"
        >
          {testar.isPending ? 'Testando...' : 'Testar Conexão'}
        </button>
        <button
          type="submit"
          disabled={salvar.isPending}
          className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 transition-colors"
        >
          {salvar.isPending ? 'Salvando...' : 'Salvar'}
        </button>
      </div>
    </form>
  )
}
```

- [ ] **Step 2: Write `ConfiguracoesLdap.jsx`**

```jsx
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ShieldCheck, ChevronRight } from 'lucide-react'
import * as unidadesApi from '../../api/unidades'
import * as ldapApi from '../../api/unidadeLdapConfig'
import Modal from '../../components/ui/Modal'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import UnidadeLdapConfigForm from './UnidadeLdapConfigForm'

function useLdapConfig(unidadeId, habilitado) {
  return useQuery({
    queryKey: ['unidade-ldap-config', unidadeId],
    queryFn: () => ldapApi.buscar(unidadeId).then(r => r.data).catch(e => {
      if (e.response?.status === 404) return null
      throw e
    }),
    enabled: habilitado,
  })
}

function LinhaUnidade({ unidade, onEditar }) {
  const { data: config, isLoading } = useLdapConfig(unidade.id, true)

  return (
    <div
      onClick={() => !isLoading && onEditar(unidade, config)}
      className="flex items-center justify-between px-5 py-4 cursor-pointer hover:bg-gray-50 transition-colors"
    >
      <div className="flex items-center gap-3">
        <div className="w-9 h-9 rounded-lg bg-brand-50 flex items-center justify-center">
          <ShieldCheck size={18} className="text-brand-600" />
        </div>
        <div>
          <p className="text-sm font-medium text-gray-800">{unidade.nome}</p>
          {isLoading ? (
            <p className="text-xs text-gray-400 mt-0.5">Carregando...</p>
          ) : (
            <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
              !config ? 'bg-gray-100 text-gray-500'
                : config.ativo ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'
            }`}>
              {!config ? 'Não configurada' : config.ativo ? 'Configurada e ativa' : 'Configurada (inativa)'}
            </span>
          )}
        </div>
      </div>
      <ChevronRight size={16} className="text-gray-300 shrink-0" />
    </div>
  )
}

export default function ConfiguracoesLdap() {
  const [unidadeSelecionada, setUnidadeSelecionada] = useState(null)
  const [configSelecionada, setConfigSelecionada] = useState(null)

  const { data, isLoading } = useQuery({
    queryKey: ['unidades'],
    queryFn: () => unidadesApi.listar().then(r => r.data),
  })

  const abrirEdicao = (unidade, config) => {
    setUnidadeSelecionada(unidade)
    setConfigSelecionada(config)
  }

  const fechar = () => {
    setUnidadeSelecionada(null)
    setConfigSelecionada(null)
  }

  if (isLoading) return <LoadingSpinner />

  const lista = data ?? []

  return (
    <div className="space-y-4">
      <p className="text-sm text-gray-500">
        Configure a conexão LDAP/AD de cada unidade para permitir login de solicitantes com usuário de rede.
        No login, o sistema tenta autenticar contra cada unidade ativa até uma responder.
      </p>

      <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100 overflow-hidden">
        {lista.map(unidade => (
          <LinhaUnidade key={unidade.id} unidade={unidade} onEditar={abrirEdicao} />
        ))}
      </div>

      <Modal
        open={!!unidadeSelecionada}
        onClose={fechar}
        title={`Configuração LDAP — ${unidadeSelecionada?.nome ?? ''}`}
        size="lg"
      >
        {unidadeSelecionada && (
          <UnidadeLdapConfigForm
            unidadeId={unidadeSelecionada.id}
            config={configSelecionada}
            onSuccess={fechar}
          />
        )}
      </Modal>
    </div>
  )
}
```

- [ ] **Step 3: Build and manually smoke-test**

Run: `npm run build`
Expected: build succeeds.

Then start the app (`composer dev`), log in as admin, go to Configurações → LDAP por Unidade, confirm:
- The unit list loads with "Não configurada" badges.
- Opening a unit shows the form; filling host/port/base_dn/username/password/valores_ad and clicking "Testar Conexão" shows an error Alert (since no real AD is reachable in dev) without crashing.
- Clicking "Salvar" persists the config (verify via `GET /api/unidades/{id}/ldap-config` in the Network tab or `php artisan tinker`), and reopening the same unit shows "Configurada e ativa" with the password field blank.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/configuracoes/ConfiguracoesLdap.jsx resources/js/pages/configuracoes/UnidadeLdapConfigForm.jsx
git commit -m "feat: adiciona tela de configuracao LDAP por unidade com teste de conexao"
```

---

### Task 10: Update the onboarding runbook

**Files:**
- Modify: `docs/runbooks/onboarding-unidade-ldap.md`

- [ ] **Step 1: Rewrite the steps that no longer apply**

Replace "Passo 1 — Configurar `.env` de produção" and "Passo 4 — Cadastrar o(s) mapeamento(s) de unidade" with:

```markdown
## Passo 1 — Cadastrar a configuração pela tela de Configurações

Não é mais necessário editar `.env` nem reiniciar/cachear config. Como Admin,
acesse **Configurações → LDAP por Unidade**, selecione a unidade e preencha
host, porta, Base DN, usuário/senha da conta de serviço, SSL/StartTLS e o(s)
valor(es) do atributo de unidade (Passo 3 abaixo ainda determina qual
atributo e valores usar). Use o botão **Testar Conexão** antes de salvar.
```

and

```markdown
## Passo 4 — Preencher o(s) valor(es) de unidade na tela

Com o(s) valor(es) do atributo identificados no Passo 3, informe-os no campo
"Valores do atributo que identificam esta unidade" da tela de Configurações
(múltiplos valores separados por vírgula), e salve.
```

Update the "Contexto importante" section at the top to state that the system now supports one LDAP connection per unit natively via the Configurações screen, removing the note about needing to extend `config/ldap.php`.

- [ ] **Step 2: Commit**

```bash
git add docs/runbooks/onboarding-unidade-ldap.md
git commit -m "docs: atualiza runbook de onboarding LDAP para o fluxo por unidade via UI"
```

---

## Self-Review Notes

- **Spec coverage:** table+model (Task 1), data migration (Task 2), CRUD (Task 3), test-connection (Task 4), multi-unit login (Task 5), legacy cleanup (Task 6), frontend hub/menu (Task 8), LDAP config screen + test button (Task 9), runbook update (Task 10) — all spec sections have a task.
- **Type consistency checked:** `UnidadeLdapConfiguracao::paraConexaoLdap()` (Task 1) is the single source of the LdapRecord connection-array shape, reused conceptually (not literally, since it needs unsaved request data) in Task 4's `testar()` and Task 5's `resolverLoginLdap()`. `resolverLoginLdap`'s return contract (`null` / `[]` / array) is documented inline and consumed exactly that way in `loginAd()`.
- **No placeholders:** every step has literal code; the one deliberate placeholder component (Task 8 Step 3) is explicitly a stopgap replaced whole by Task 9 Step 2, not a TODO left unresolved by the end of the plan.
