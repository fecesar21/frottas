# Login via LDAP/Active Directory para solicitantes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que qualquer um dos 1000+ funcionários da empresa faça login no app de solicitação de transporte usando seu usuário e senha de rede (Windows/AD), sem cadastro manual prévio.

**Architecture:** Novo endpoint `POST /api/auth/login-ad` no `AuthController` existente, usando `directorytree/ldaprecord-laravel` para localizar o usuário no AD e validar a senha via bind LDAP direto. No primeiro login bem-sucedido, o sistema cria automaticamente um registro `Usuario` (perfil `solicitante`) correlacionado pelo `objectGUID` do AD, com a unidade resolvida a partir de uma tabela de mapeamento administrável. O app `solicitacao-js` passa a chamar esse novo endpoint em vez do login CPF+senha local. Nenhuma mudança nos logins de admin/gestor/operador.

**Tech Stack:** Laravel 11, `directorytree/ldaprecord-laravel`, `doctrine/dbal` (para alterar enum em SQLite), Sanctum, PHPUnit, React (app `solicitacao-js`).

## Global Constraints

- Bind de validação de senha SEMPRE via bind direto do DN do usuário — nunca comparar hash nem usar a service account para autenticar o solicitante (spec: "Backend" §`AuthController@loginAd`).
- Conexão ao AD via LDAPS (porta 636) — spec: "Configuração".
- Mensagem de erro de credencial inválida deve ser genérica: `"Usuário ou senha inválidos"` — nunca revelar se o usuário existe ou não (spec: "Tratamento de erros").
- Login de `admin`/`gestor`/`operador` via `/api/auth/login` permanece inalterado — nenhuma tarefa deste plano deve tocar `AuthController::login()` ou `LoginRequest` existentes.
- Rate limit: reutilizar o limiter nomeado `login` já definido em `AppServiceProvider` (5/min por IP) — não criar um novo limiter (spec: "Rotas").
- `cpf` e `senha_hash` devem aceitar `null` — solicitantes não têm CPF nem senha local (spec: "Migrations" item 2).

---

## File Structure

- **Modify** `composer.json` — adiciona `directorytree/ldaprecord-laravel` e `doctrine/dbal`.
- **Create** `config/ldap.php` — publicado pelo pacote, adiciona conexão `default` com variáveis de `.env`.
- **Modify** `.env.example` — documenta as novas variáveis `LDAP_*`.
- **Create** `database/migrations/..._change_perfil_enum_add_solicitante.php` — adiciona `solicitante` ao enum, torna `cpf`/`senha_hash` nullable.
- **Create** `database/migrations/..._add_ldap_fields_to_usuarios_table.php` — adiciona `ldap_guid`, `ldap_sync_at`.
- **Create** `database/migrations/..._create_unidade_ad_mapeamentos_table.php`.
- **Create** `app/Models/UnidadeAdMapeamento.php`.
- **Modify** `app/Models/Usuario.php` — adiciona `ldap_guid`, `ldap_sync_at` ao `$fillable`.
- **Create** `app/Http/Requests/Auth/LoginAdRequest.php`.
- **Modify** `app/Http/Controllers/Api/AuthController.php` — adiciona método `loginAd()`.
- **Modify** `routes/api.php` — adiciona rota `POST /api/auth/login-ad`.
- **Create** `tests/Feature/Auth/LoginAdTest.php`.
- **Modify** `resources/solicitacao-js/api/auth.js` — `login()` chama `/auth/login-ad`.
- **Modify** `resources/solicitacao-js/pages/Login.jsx` — placeholder do campo usuário.

---

### Task 1: Instalar dependências e configurar conexão LDAP

**Files:**
- Modify: `composer.json`
- Create: `config/ldap.php` (publicado via artisan, depois editado)
- Modify: `.env.example`

**Interfaces:**
- Produces: `config('ldap.connections.default')` com chaves `hosts`, `base_dn`, `username`, `password`, `port`, `use_ssl`; `config('ldap.unidade_attribute')` (string, nome do atributo AD usado para unidade).

- [ ] **Step 1: Instalar os pacotes**

Run: `composer require directorytree/ldaprecord-laravel doctrine/dbal`

Expected: comando termina sem erro, `composer.json` e `composer.lock` atualizados com as duas dependências.

- [ ] **Step 2: Publicar a config do LDAPRecord**

Run: `php artisan vendor:publish --tag=ldap-config`

Expected: cria `config/ldap.php` com uma conexão `default` de exemplo.

- [ ] **Step 3: Ajustar `config/ldap.php` para ler do `.env` e adicionar a chave `unidade_attribute`**

Abrir `config/ldap.php`, garantir que a conexão `default` está assim (o publish já gera algo próximo, ajustar os nomes das env vars para o padrão do projeto):

```php
<?php

return [
    'default' => env('LDAP_CONNECTION', 'default'),

    'connections' => [
        'default' => [
            'hosts'    => [env('LDAP_HOST', '')],
            'username' => env('LDAP_USERNAME', ''),
            'password' => env('LDAP_PASSWORD', ''),
            'port'     => env('LDAP_PORT', 636),
            'base_dn'  => env('LDAP_BASE_DN', ''),
            'timeout'  => env('LDAP_TIMEOUT', 5),
            'use_ssl'  => env('LDAP_USE_SSL', true),
            'use_tls'  => env('LDAP_USE_TLS', false),
        ],
    ],

    'logging' => [
        'enabled'  => env('LDAP_LOGGING', true),
        'channel'  => env('LOG_CHANNEL', 'stack'),
        'level'    => 'info',
    ],

    'cache' => [
        'enabled' => false,
    ],

    // Atributo do AD usado para resolver a unidade do solicitante
    // (ex.: department, company) — ver App\Models\UnidadeAdMapeamento.
    'unidade_attribute' => env('LDAP_UNIDADE_ATTRIBUTE', 'department'),
];
```

- [ ] **Step 4: Documentar as variáveis no `.env.example`**

Adicionar ao final de `.env.example`:

```
# LDAP / Active Directory (login de solicitantes)
LDAP_HOST=
LDAP_BASE_DN=
LDAP_USERNAME=
LDAP_PASSWORD=
LDAP_PORT=636
LDAP_USE_SSL=true
LDAP_UNIDADE_ATTRIBUTE=department
```

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock config/ldap.php .env.example
git commit -m "chore: instala e configura ldaprecord-laravel para login de solicitantes"
```

---

### Task 2: Migrations — perfil `solicitante`, campos LDAP e tabela de mapeamento de unidade

**Files:**
- Create: `database/migrations/2026_08_05_000001_add_solicitante_to_usuarios_perfil_enum.php`
- Create: `database/migrations/2026_08_05_000002_add_ldap_fields_to_usuarios_table.php`
- Create: `database/migrations/2026_08_05_000003_create_unidade_ad_mapeamentos_table.php`
- Test: `tests/Feature/Auth/LoginAdTest.php` (criado no Task 5, mas valida o schema deste task)

**Interfaces:**
- Produces: coluna `usuarios.perfil` aceita `'solicitante'`; colunas `usuarios.cpf` e `usuarios.senha_hash` nullable; colunas `usuarios.ldap_guid` (string, nullable, unique) e `usuarios.ldap_sync_at` (timestamp, nullable); tabela `unidade_ad_mapeamentos` com colunas `id` (uuid pk), `valor_ad` (string, unique), `unidade_id` (uuid fk → `unidades.id`), timestamps.

- [ ] **Step 1: Criar a migration do enum de perfil**

```php
<?php
// database/migrations/2026_08_05_000001_add_solicitante_to_usuarios_perfil_enum.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite não impõe o enum de fato (é um CHECK constraint), então
            // não há coluna a alterar via schema builder; o valor extra já é
            // aceito pela coluna TEXT subjacente. Nada a fazer aqui além de
            // registrar a migration para manter o histórico em sincronia
            // entre ambientes.
            return;
        }

        DB::statement("ALTER TABLE usuarios MODIFY perfil ENUM('admin','gestor','operador','solicitante') DEFAULT 'operador'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE usuarios MODIFY perfil ENUM('admin','gestor','operador') DEFAULT 'operador'");
    }
};
```

- [ ] **Step 2: Criar a migration dos campos nullable e campos LDAP**

```php
<?php
// database/migrations/2026_08_05_000002_add_ldap_fields_to_usuarios_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('ldap_guid')->nullable()->unique()->after('cpf');
            $table->timestamp('ldap_sync_at')->nullable()->after('ldap_guid');
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('senha_hash')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('senha_hash')->nullable(false)->change();
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['ldap_guid', 'ldap_sync_at']);
        });
    }
};
```

Nota: `cpf` já é `nullable()` desde a migration `2026_06_23_000015_add_cpf_to_usuarios_table.php` — não precisa de alteração.

- [ ] **Step 3: Criar a migration da tabela de mapeamento**

```php
<?php
// database/migrations/2026_08_05_000003_create_unidade_ad_mapeamentos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidade_ad_mapeamentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('valor_ad')->unique();
            $table->foreignUuid('unidade_id')->constrained('unidades');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidade_ad_mapeamentos');
    }
};
```

- [ ] **Step 4: Rodar as migrations**

Run: `php artisan migrate`
Expected: as 3 migrations aplicam sem erro; `php artisan migrate:status` mostra as 3 como `Ran`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_05_000001_add_solicitante_to_usuarios_perfil_enum.php database/migrations/2026_08_05_000002_add_ldap_fields_to_usuarios_table.php database/migrations/2026_08_05_000003_create_unidade_ad_mapeamentos_table.php
git commit -m "feat: adiciona perfil solicitante, campos ldap e tabela de mapeamento de unidade"
```

---

### Task 3: Model `UnidadeAdMapeamento` e ajuste do `Usuario`

**Files:**
- Create: `app/Models/UnidadeAdMapeamento.php`
- Modify: `app/Models/Usuario.php:28-30` (bloco `$fillable`)
- Test: `tests/Unit/Models/UnidadeAdMapeamentoTest.php`

**Interfaces:**
- Consumes: nada de tasks anteriores além do schema criado no Task 2.
- Produces: `UnidadeAdMapeamento::where('valor_ad', $valor)->first()?->unidade_id` (usado no Task 5); `Usuario::create([...'ldap_guid' => ..., 'ldap_sync_at' => ...])` aceito pelo `$fillable`.

- [ ] **Step 1: Escrever o teste de unidade do model**

```php
<?php
// tests/Unit/Models/UnidadeAdMapeamentoTest.php

namespace Tests\Unit\Models;

use App\Models\Unidade;
use App\Models\UnidadeAdMapeamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnidadeAdMapeamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_unidade_pelo_valor_ad(): void
    {
        $unidade = Unidade::factory()->create();

        UnidadeAdMapeamento::create([
            'valor_ad'   => 'HOSP-CENTRO',
            'unidade_id' => $unidade->id,
        ]);

        $mapeamento = UnidadeAdMapeamento::where('valor_ad', 'HOSP-CENTRO')->first();

        $this->assertNotNull($mapeamento);
        $this->assertSame($unidade->id, $mapeamento->unidade_id);
        $this->assertTrue($mapeamento->unidade->is($unidade));
    }
}
```

- [ ] **Step 2: Rodar o teste para confirmar que falha**

Run: `php artisan test tests/Unit/Models/UnidadeAdMapeamentoTest.php`
Expected: FAIL — `Class "App\Models\UnidadeAdMapeamento" not found`.

- [ ] **Step 3: Criar o model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnidadeAdMapeamento extends Model
{
    use HasUuids;

    protected $table = 'unidade_ad_mapeamentos';

    protected $fillable = ['valor_ad', 'unidade_id'];

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }
}
```

- [ ] **Step 4: Ajustar o `$fillable` de `Usuario`**

Em `app/Models/Usuario.php:28-30`, trocar:

```php
    protected $fillable = [
        'nome', 'cpf', 'email', 'senha_hash', 'perfil', 'ativo', 'ultimo_acesso', 'motorista_id', 'unidade_id',
    ];
```

por:

```php
    protected $fillable = [
        'nome', 'cpf', 'email', 'senha_hash', 'perfil', 'ativo', 'ultimo_acesso', 'motorista_id', 'unidade_id',
        'ldap_guid', 'ldap_sync_at',
    ];
```

- [ ] **Step 5: Rodar o teste e confirmar que passa**

Run: `php artisan test tests/Unit/Models/UnidadeAdMapeamentoTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Models/UnidadeAdMapeamento.php app/Models/Usuario.php tests/Unit/Models/UnidadeAdMapeamentoTest.php
git commit -m "feat: model UnidadeAdMapeamento e campos ldap no fillable de Usuario"
```

---

### Task 4: `LoginAdRequest`

**Files:**
- Create: `app/Http/Requests/Auth/LoginAdRequest.php`

**Interfaces:**
- Produces: `LoginAdRequest::validated()` retorna `['usuario' => string, 'senha' => string]` — mesmo shape do `LoginRequest` existente, consumido pelo Task 5.

- [ ] **Step 1: Criar o Form Request**

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usuario' => 'required|string',
            'senha'   => 'required|string',
        ];
    }
}
```

Este request não tem lógica própria a testar (mesma estrutura do `LoginRequest` existente, sem normalização) — sua cobertura vem dos testes de integração do endpoint no Task 5.

- [ ] **Step 2: Commit**

```bash
git add app/Http/Requests/Auth/LoginAdRequest.php
git commit -m "feat: LoginAdRequest para o endpoint de login LDAP"
```

---

### Task 5: `AuthController::loginAd`, rota e testes de integração

**Files:**
- Modify: `app/Http/Controllers/Api/AuthController.php`
- Modify: `routes/api.php:31` (grupo `auth`)
- Test: `tests/Feature/Auth/LoginAdTest.php`

**Interfaces:**
- Consumes: `LoginAdRequest::validated()` (Task 4); `UnidadeAdMapeamento::where('valor_ad', ...)->first()` (Task 3); `Usuario::firstOrNew(['ldap_guid' => ...])` com `$fillable` do Task 3; `config('ldap.unidade_attribute')` (Task 1).
- Produces: `POST /api/auth/login-ad` → `200 { token, user }` ou `401 { error }` ou `503 { error }`.

- [ ] **Step 1: Escrever os testes de integração usando o `DirectoryEmulator` do LDAPRecord**

```php
<?php
// tests/Feature/Auth/LoginAdTest.php

namespace Tests\Feature\Auth;

use App\Models\Unidade;
use App\Models\UnidadeAdMapeamento;
use App\Models\Usuario;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use Tests\TestCase;

class LoginAdTest extends TestCase
{
    protected $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = DirectoryEmulator::setup('default');
    }

    protected function tearDown(): void
    {
        DirectoryEmulator::tearDown();
        parent::tearDown();
    }

    private function criarUsuarioLdap(array $attrs = []): LdapUser
    {
        $user = LdapUser::create(array_merge([
            'cn'              => 'João Silva',
            'displayname'     => 'João Silva',
            'samaccountname'  => 'jsilva',
            'mail'            => 'jsilva@empresa.com.br',
            'department'      => 'HOSP-CENTRO',
            'objectguid'      => \Illuminate\Support\Str::uuid()->toString(),
        ], $attrs));

        $this->fake->actingAs($user);

        return $user;
    }

    public function test_login_ad_com_credenciais_validas_cria_usuario_solicitante(): void
    {
        $unidade = Unidade::factory()->create();
        UnidadeAdMapeamento::create(['valor_ad' => 'HOSP-CENTRO', 'unidade_id' => $unidade->id]);
        $this->criarUsuarioLdap();

        $response = $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha'   => 'senha-correta',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'nome', 'email', 'perfil']])
            ->assertJsonPath('user.perfil', 'solicitante')
            ->assertJsonPath('user.unidade_id', $unidade->id);

        $this->assertDatabaseHas('usuarios', [
            'email'  => 'jsilva@empresa.com.br',
            'perfil' => 'solicitante',
        ]);
    }

    public function test_login_ad_com_usuario_existente_atualiza_dados(): void
    {
        $ldapUser = $this->criarUsuarioLdap();
        $guid = $ldapUser->getConvertedGuid();

        $usuario = Usuario::factory()->create([
            'ldap_guid' => $guid,
            'nome'      => 'Nome Antigo',
            'perfil'    => 'solicitante',
        ]);

        $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha'   => 'senha-correta',
        ])->assertOk();

        $this->assertDatabaseHas('usuarios', [
            'id'   => $usuario->id,
            'nome' => 'João Silva',
        ]);
    }

    public function test_login_ad_com_senha_incorreta_retorna_401(): void
    {
        $this->criarUsuarioLdap();
        $this->fake->getLdapConnection()->shouldBlock('jsilva');

        $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha'   => 'senha-errada',
        ])->assertUnauthorized()
          ->assertJson(['error' => 'Usuário ou senha inválidos']);
    }

    public function test_login_ad_com_usuario_inexistente_retorna_401(): void
    {
        $this->postJson('/api/auth/login-ad', [
            'usuario' => 'naoexiste',
            'senha'   => 'qualquer',
        ])->assertUnauthorized()
          ->assertJson(['error' => 'Usuário ou senha inválidos']);
    }

    public function test_login_ad_sem_mapeamento_de_unidade_cria_usuario_com_unidade_nula(): void
    {
        $this->criarUsuarioLdap(['department' => 'SETOR-SEM-MAPEAMENTO']);

        $response = $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha'   => 'senha-correta',
        ]);

        $response->assertOk()->assertJsonPath('user.unidade_id', null);
    }
}
```

- [ ] **Step 2: Rodar os testes para confirmar que falham**

Run: `php artisan test tests/Feature/Auth/LoginAdTest.php`
Expected: FAIL — rota `login-ad` não existe (404) e/ou método `loginAd` não existe.

- [ ] **Step 3: Implementar `AuthController::loginAd`**

Em `app/Http/Controllers/Api/AuthController.php`, adicionar os imports necessários no topo:

```php
use App\Http\Requests\Auth\LoginAdRequest;
use App\Models\UnidadeAdMapeamento;
use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Auth\ListensForLdapBindFailure;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
```

E adicionar o método (após `login()`):

```php
    public function loginAd(LoginAdRequest $r)
    {
        $input = $r->validated();

        try {
            $ldapUser = LdapUser::findBy('samaccountname', $input['usuario']);
        } catch (\LdapRecord\LdapRecordException $e) {
            Log::error('Falha ao consultar o AD', ['erro' => $e->getMessage()]);
            return response()->json(['error' => 'Serviço de autenticação indisponível, tente novamente'], 503);
        }

        if (! $ldapUser) {
            return response()->json(['error' => 'Usuário ou senha inválidos'], 401);
        }

        try {
            $binded = $ldapUser->getConnection()->auth()->attempt($ldapUser->getDn(), $input['senha']);
        } catch (\LdapRecord\LdapRecordException $e) {
            Log::error('Falha ao conectar ao AD para bind', ['erro' => $e->getMessage()]);
            return response()->json(['error' => 'Serviço de autenticação indisponível, tente novamente'], 503);
        }

        if (! $binded) {
            return response()->json(['error' => 'Usuário ou senha inválidos'], 401);
        }

        $guid = $ldapUser->getConvertedGuid();
        $valorAd = $ldapUser->getFirstAttribute(config('ldap.unidade_attribute'));
        $unidadeId = UnidadeAdMapeamento::where('valor_ad', $valorAd)->first()?->unidade_id;

        $usuario = Usuario::firstOrNew(['ldap_guid' => $guid]);
        $usuario->fill([
            'nome'         => $ldapUser->getFirstAttribute('displayname'),
            'email'        => $ldapUser->getFirstAttribute('mail'),
            'perfil'       => 'solicitante',
            'ativo'        => true,
            'unidade_id'   => $unidadeId,
            'ldap_sync_at' => now(),
        ]);
        $usuario->save();

        $usuario->update(['ultimo_acesso' => now()]);
        $token = $usuario->createToken('app', [], now()->addHours(8))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->buildUserPayload($usuario),
        ]);
    }
```

- [ ] **Step 4: Adicionar a rota**

Em `routes/api.php:31`, dentro do grupo `Route::prefix('auth')`, logo abaixo da rota `login`:

```php
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('login-ad', [AuthController::class, 'loginAd'])->middleware('throttle:login');
```

- [ ] **Step 5: Rodar os testes e confirmar que passam**

Run: `php artisan test tests/Feature/Auth/LoginAdTest.php`
Expected: PASS (5 testes).

- [ ] **Step 6: Rodar a suíte completa de Auth para garantir que nada quebrou**

Run: `php artisan test tests/Feature/Auth`
Expected: PASS — inclui `AuthTest.php` e `LoginRateLimitTest.php` inalterados.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/AuthController.php routes/api.php tests/Feature/Auth/LoginAdTest.php
git commit -m "feat: endpoint POST /api/auth/login-ad com bind LDAP e auto-provisionamento"
```

---

### Task 6: Frontend `solicitacao-js` — usar o novo endpoint

**Files:**
- Modify: `resources/solicitacao-js/api/auth.js:3`
- Modify: `resources/solicitacao-js/pages/Login.jsx:47-54`

**Interfaces:**
- Consumes: `POST /api/auth/login-ad` (Task 5), mesmo shape de resposta `{ token, user }` que `authApi.login()` já espera — nenhuma mudança necessária em `AuthContext.jsx`.

- [ ] **Step 1: Trocar o endpoint chamado**

Em `resources/solicitacao-js/api/auth.js:3`, trocar:

```js
export const login = (data) => api.post('/auth/login', data)
```

por:

```js
export const login = (data) => api.post('/auth/login-ad', data)
```

- [ ] **Step 2: Atualizar o placeholder e o label do campo de usuário**

Em `resources/solicitacao-js/pages/Login.jsx:43-55`, trocar:

```jsx
                <label className="block text-sm font-medium text-gray-700 mb-1.5">Usuário</label>
                <div className="relative">
                  <User size={16} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                  <input
                    type="text"
                    required
                    autoFocus
                    value={form.usuario}
                    onChange={(e) => setForm(f => ({ ...f, usuario: e.target.value }))}
                    className="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400/40 focus:border-brand-400"
                    placeholder="usuário ou e-mail"
                  />
```

por:

```jsx
                <label className="block text-sm font-medium text-gray-700 mb-1.5">Usuário de rede</label>
                <div className="relative">
                  <User size={16} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                  <input
                    type="text"
                    required
                    autoFocus
                    value={form.usuario}
                    onChange={(e) => setForm(f => ({ ...f, usuario: e.target.value }))}
                    className="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400/40 focus:border-brand-400"
                    placeholder="mesmo usuário do computador"
                  />
```

E o rótulo "Senha" logo abaixo (linha ~59) para "Senha de rede":

```jsx
                <label className="block text-sm font-medium text-gray-700 mb-1.5">Senha de rede</label>
```

- [ ] **Step 3: Build do frontend e verificação manual**

Run: `npm run build`
Expected: build conclui sem erro.

Testar manualmente: abrir o app de solicitação, tentar logar com um usuário de teste — como o AD real não está disponível em dev, validar ao menos que a chamada vai para `/api/auth/login-ad` (inspecionar aba Network do browser) e que a mensagem de erro genérica aparece corretamente quando a API retorna 401.

- [ ] **Step 4: Commit**

```bash
git add resources/solicitacao-js/api/auth.js resources/solicitacao-js/pages/Login.jsx
git commit -m "feat: app de solicitacao usa login via AD (login-ad)"
```

---

## Manual Verification Checklist (produção)

Antes de considerar o rollout completo, validar em ambiente real (não coberto por testes automatizados, pois dependem do AD/DC real):

1. Preencher as variáveis `LDAP_*` reais no `.env` de produção e confirmar que `php artisan tinker` consegue rodar `\LdapRecord\Models\ActiveDirectory\User::findBy('samaccountname', 'algum_usuario_real')` sem erro de conexão.
2. Popular `unidade_ad_mapeamentos` com os valores reais do atributo `department`/`company` usados na empresa (via `php artisan tinker` ou seeder específico do ambiente).
3. Fazer um login real de teste no app de solicitação com um usuário de rede válido e confirmar criação do registro em `usuarios` com `perfil = solicitante` e `unidade_id` correto.
4. Testar um login com senha errada e confirmar bloqueio consistente com a política do AD (sem múltiplos binds desnecessários).
