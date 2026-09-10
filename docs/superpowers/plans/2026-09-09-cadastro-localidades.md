# Cadastro de Localidades Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que o admin cadastre "Localidades" (locais externos à rede de unidades, com endereço/coordenadas/contato) e que elas apareçam, junto com as Unidades, como opções de Origem/Destino nos motivos "Transferência de Paciente" e "Transporte de Colaboradores" da tela de Nova Solicitação.

**Architecture:** Nova tabela/model `Localidade` com CRUD admin-only (espelhando `UnidadeController`). `solicitacoes.origem_unidade_id`/`destino_unidade_id` viram colunas polimórficas `origem_tipo`/`origem_id` e `destino_tipo`/`destino_id`. Um novo endpoint agregador `GET /pontos-viagem` combina unidades e localidades ativas numa lista única, consumida pela tela de Nova Solicitação (app `resources/solicitacao-js`). Uma nova seção "Localidades" é adicionada ao hub de Configurações já existente no admin (`resources/js/pages/configuracoes/ConfiguracoesHub.jsx`), em `/configuracoes/localidades`.

**Tech Stack:** Laravel 11 (PHP), SQLite, React + `@tanstack/react-query` (admin `resources/js`), React simples com `useState`/`fetch` via axios (`resources/solicitacao-js`), PHPUnit.

**Spec:** [docs/superpowers/specs/2026-09-09-cadastro-localidades-design.md](../specs/2026-09-09-cadastro-localidades-design.md)

## Global Constraints

- UUID como chave primária em todo model novo, via trait `HasUuids` (`$incrementing = false`, `$keyType = 'string'`), seguindo o padrão de `Unidade`.
- Toda escrita (`store`/`update`/`destroy`) em `Localidade` exige `$request->user()->perfil === 'admin'`, igual a `UnidadeController`.
- `destroy` de Localidade é soft: apenas seta `ativo = false`. Nunca apagar a linha.
- Localidades são uma lista global (não vinculadas a unidade específica) — decisão validada com o usuário durante o brainstorming.
- Contato da Localidade é simples: um `telefone` e um `email`, sem lista de múltiplos contatos.
- **Ruling (pré-flight, corrige a spec):** a spec foi escrita contra uma cópia local do branch `main` que estava desatualizada; nela, o hub de Configurações (`ConfiguracoesHub.jsx`, feature de LDAP por unidade) ainda não tinha sido mesclado. No branch real usado para esta implementação (`origin/main`, commit `9be591b7`), o hub **já existe** com uma seção "LDAP por Unidade" em `/configuracoes/ldap`. Este plano usa o hub real: a Task 5 adiciona uma seção "Localidades" a `ConfiguracoesHub.jsx` e uma rota `/configuracoes/localidades`, em vez de uma página `/localidades` de nível superior. Custo se este ruling estiver errado: a tela fica um passo a mais dentro do hub em vez de direto na sidebar — reversível trivialmente movendo a rota e o item de menu.

---

## Task 1: Migration e Model `Localidade`

**Files:**
- Create: `database/migrations/2026_09_09_000001_create_localidades_table.php`
- Create: `app/Models/Localidade.php`
- Create: `database/factories/LocalidadeFactory.php`
- Test: `tests/Unit/Models/LocalidadeTest.php`

**Interfaces:**
- Produces: model `App\Models\Localidade` com `$fillable = ['nome', 'endereco', 'latitude', 'longitude', 'telefone', 'email', 'ativo']`, UUID primary key, tabela `localidades`.

- [ ] **Step 1: Escrever o teste (falha esperada — model ainda não existe)**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Localidade;
use Tests\TestCase;

class LocalidadeTest extends TestCase
{
    public function test_cria_localidade_com_uuid_e_ativo_por_padrao(): void
    {
        $localidade = Localidade::create([
            'nome' => 'Clínica Parceira ABC',
            'endereco' => 'Rua das Flores, 100',
            'latitude' => -23.5505,
            'longitude' => -46.6333,
            'telefone' => '(11) 4002-8922',
            'email' => 'contato@clinicaabc.com.br',
        ]);

        $this->assertTrue((bool) preg_match('/^[0-9a-f-]{36}$/', $localidade->id));
        $this->assertTrue($localidade->ativo);
        $this->assertSame('Clínica Parceira ABC', $localidade->fresh()->nome);
    }
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `php artisan test tests/Unit/Models/LocalidadeTest.php`
Expected: FAIL — `Class "App\Models\Localidade" not found` (ou tabela `localidades` inexistente).

- [ ] **Step 3: Criar a migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('localidades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome', 150);
            $table->string('endereco', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('email')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('localidades');
    }
};
```

- [ ] **Step 4: Criar o model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Localidade extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'localidades';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['nome', 'endereco', 'latitude', 'longitude', 'telefone', 'email', 'ativo'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'ativo' => 'boolean',
    ];
}
```

- [ ] **Step 5: Criar a factory**

```php
<?php

namespace Database\Factories;

use App\Models\Localidade;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocalidadeFactory extends Factory
{
    protected $model = Localidade::class;

    public function definition(): array
    {
        return [
            'nome' => $this->faker->company(),
            'endereco' => $this->faker->streetAddress(),
            'latitude' => $this->faker->latitude(-33, 5),
            'longitude' => $this->faker->longitude(-73, -34),
            'telefone' => $this->faker->numerify('(##) ####-####'),
            'email' => $this->faker->safeEmail(),
            'ativo' => true,
        ];
    }
}
```

- [ ] **Step 6: Rodar a migration e o teste, confirmar que passa**

Run: `php artisan migrate && php artisan test tests/Unit/Models/LocalidadeTest.php`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_09_000001_create_localidades_table.php app/Models/Localidade.php database/factories/LocalidadeFactory.php tests/Unit/Models/LocalidadeTest.php
git commit -m "feat: adiciona model e migration de Localidade

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01YFrj2A7MTMbtfxyPbtxvYb"
```

---

## Task 2: CRUD API de Localidade (`LocalidadeController`)

**Files:**
- Create: `app/Http/Controllers/Api/LocalidadeController.php`
- Modify: `routes/api.php:120` (logo após o bloco `// Unidades`)
- Test: `tests/Feature/Localidade/LocalidadeApiTest.php`

**Interfaces:**
- Consumes: `App\Models\Localidade` (Task 1).
- Produces: rotas `GET/POST /api/localidades`, `GET/PATCH/DELETE /api/localidades/{localidade}`. `PATCH` (não `PUT`) para bater com o padrão de `unidades` no `routes/api.php` e com o client axios `atualizar()` que outras telas admin usam.

- [ ] **Step 1: Escrever o teste (falha esperada — controller/rotas ainda não existem)**

```php
<?php

namespace Tests\Feature\Localidade;

use App\Models\Localidade;
use Tests\TestCase;

class LocalidadeApiTest extends TestCase
{
    public function test_admin_lista_localidades(): void
    {
        $this->loginAdmin();
        Localidade::factory()->count(2)->create();

        $this->getJson('/api/localidades')->assertOk()->assertJsonCount(2);
    }

    public function test_admin_cria_localidade(): void
    {
        $this->loginAdmin();

        $response = $this->postJson('/api/localidades', [
            'nome' => 'Hospital Parceiro Norte',
            'endereco' => 'Av. Brasil, 500',
            'latitude' => -23.5,
            'longitude' => -46.6,
            'telefone' => '(11) 3333-4444',
            'email' => 'contato@hospitalnorte.com.br',
        ]);

        $response->assertCreated()->assertJsonPath('nome', 'Hospital Parceiro Norte');
        $this->assertDatabaseHas('localidades', ['nome' => 'Hospital Parceiro Norte', 'ativo' => true]);
    }

    public function test_operador_nao_pode_criar_localidade(): void
    {
        $this->loginOperador();

        $this->postJson('/api/localidades', ['nome' => 'Teste'])->assertForbidden();
    }

    public function test_admin_atualiza_localidade(): void
    {
        $this->loginAdmin();
        $localidade = Localidade::factory()->create(['nome' => 'Nome Antigo']);

        $this->patchJson("/api/localidades/{$localidade->id}", ['nome' => 'Nome Novo'])
            ->assertOk()
            ->assertJsonPath('nome', 'Nome Novo');
    }

    public function test_admin_desativa_localidade_sem_apagar(): void
    {
        $this->loginAdmin();
        $localidade = Localidade::factory()->create();

        $this->deleteJson("/api/localidades/{$localidade->id}")->assertOk();

        $this->assertDatabaseHas('localidades', ['id' => $localidade->id, 'ativo' => false]);
    }

    public function test_criacao_exige_nome(): void
    {
        $this->loginAdmin();

        $this->postJson('/api/localidades', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nome']);
    }
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `php artisan test tests/Feature/Localidade/LocalidadeApiTest.php`
Expected: FAIL — rota `/api/localidades` inexistente (404).

- [ ] **Step 3: Criar o controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Localidade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocalidadeController extends Controller
{
    public function index(): JsonResponse
    {
        $localidades = Localidade::orderBy('nome')->get();

        return response()->json($localidades);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request);

        $data = $request->validate([
            'nome' => 'required|string|max:150',
            'endereco' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'telefone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'ativo' => 'boolean',
        ]);

        $localidade = Localidade::create($data);

        return response()->json($localidade, 201);
    }

    public function show(Localidade $localidade): JsonResponse
    {
        return response()->json($localidade);
    }

    public function update(Request $request, Localidade $localidade): JsonResponse
    {
        $this->autorizar($request);

        $data = $request->validate([
            'nome' => 'sometimes|string|max:150',
            'endereco' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'telefone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'ativo' => 'sometimes|boolean',
        ]);

        $localidade->update($data);

        return response()->json($localidade);
    }

    public function destroy(Request $request, Localidade $localidade): JsonResponse
    {
        $this->autorizar($request);

        $localidade->update(['ativo' => false]);

        return response()->json(['message' => 'Localidade desativada.']);
    }

    private function autorizar(Request $request): void
    {
        abort_unless($request->user()->perfil === 'admin', 403, 'Apenas administradores podem gerenciar localidades.');
    }
}
```

- [ ] **Step 4: Registrar as rotas**

Em `routes/api.php`, logo após a linha `Route::delete('unidades/{unidade}/veiculos/{veiculo}', [UnidadeController::class, 'desvincularVeiculo']);` (linha 124) e antes do comentário `// Notificações`, adicionar:

```php
    // Localidades
    Route::get('localidades', [LocalidadeController::class, 'index'])->name('localidades.index');
    Route::post('localidades', [LocalidadeController::class, 'store']);
    Route::get('localidades/{localidade}', [LocalidadeController::class, 'show'])->name('localidades.show');
    Route::patch('localidades/{localidade}', [LocalidadeController::class, 'update']);
    Route::delete('localidades/{localidade}', [LocalidadeController::class, 'destroy']);
```

E adicionar o `use` no topo do arquivo, junto aos demais `use App\Http\Controllers\Api\...`:

```php
use App\Http\Controllers\Api\LocalidadeController;
```

- [ ] **Step 5: Rodar o teste e confirmar que passa**

Run: `php artisan test tests/Feature/Localidade/LocalidadeApiTest.php`
Expected: PASS (6 testes)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/LocalidadeController.php routes/api.php tests/Feature/Localidade/LocalidadeApiTest.php
git commit -m "feat: adiciona CRUD admin de Localidade

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01YFrj2A7MTMbtfxyPbtxvYb"
```

---

## Task 3: Endpoint agregador `GET /pontos-viagem`

**Files:**
- Create: `app/Http/Controllers/Api/PontoViagemController.php`
- Modify: `routes/api.php` (após o bloco `// Localidades` criado na Task 2)
- Test: `tests/Feature/Localidade/PontoViagemApiTest.php`

**Interfaces:**
- Consumes: `App\Models\Unidade`, `App\Models\Localidade`.
- Produces: `GET /api/pontos-viagem` → array JSON `[{ "tipo": "unidade"|"localidade", "id": "...", "nome": "..." }]`, ordenado por `nome`, apenas registros `ativo = true`.

- [ ] **Step 1: Escrever o teste (falha esperada — endpoint ainda não existe)**

```php
<?php

namespace Tests\Feature\Localidade;

use App\Models\Localidade;
use App\Models\Unidade;
use Tests\TestCase;

class PontoViagemApiTest extends TestCase
{
    public function test_lista_unidades_e_localidades_ativas_combinadas(): void
    {
        $this->loginOperador();

        Unidade::factory()->create(['nome' => 'Hospital Central', 'ativo' => true]);
        Unidade::factory()->create(['nome' => 'Filial Desativada', 'ativo' => false]);
        Localidade::factory()->create(['nome' => 'Clínica Parceira', 'ativo' => true]);
        Localidade::factory()->create(['nome' => 'Local Inativo', 'ativo' => false]);

        $response = $this->getJson('/api/pontos-viagem')->assertOk();
        $nomes = collect($response->json())->pluck('nome')->all();

        $this->assertEqualsCanonicalizing(['Hospital Central', 'Clínica Parceira'], $nomes);
    }

    public function test_cada_item_indica_o_tipo_de_origem(): void
    {
        $this->loginOperador();

        $unidade = Unidade::factory()->create(['nome' => 'Hospital A']);
        $localidade = Localidade::factory()->create(['nome' => 'Local B']);

        $response = $this->getJson('/api/pontos-viagem')->assertOk();
        $itens = collect($response->json())->keyBy('nome');

        $this->assertSame('unidade', $itens['Hospital A']['tipo']);
        $this->assertSame($unidade->id, $itens['Hospital A']['id']);
        $this->assertSame('localidade', $itens['Local B']['tipo']);
        $this->assertSame($localidade->id, $itens['Local B']['id']);
    }
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `php artisan test tests/Feature/Localidade/PontoViagemApiTest.php`
Expected: FAIL — rota `/api/pontos-viagem` inexistente (404).

- [ ] **Step 3: Criar o controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Localidade;
use App\Models\Unidade;
use Illuminate\Http\JsonResponse;

class PontoViagemController extends Controller
{
    public function index(): JsonResponse
    {
        $unidades = Unidade::where('ativo', true)->get(['id', 'nome'])
            ->map(fn ($u) => ['tipo' => 'unidade', 'id' => $u->id, 'nome' => $u->nome]);

        $localidades = Localidade::where('ativo', true)->get(['id', 'nome'])
            ->map(fn ($l) => ['tipo' => 'localidade', 'id' => $l->id, 'nome' => $l->nome]);

        $pontos = $unidades->concat($localidades)->sortBy('nome')->values();

        return response()->json($pontos);
    }
}
```

- [ ] **Step 4: Registrar a rota**

Em `routes/api.php`, logo após o bloco de rotas `// Localidades`:

```php
    Route::get('pontos-viagem', [PontoViagemController::class, 'index']);
```

E adicionar o `use`:

```php
use App\Http\Controllers\Api\PontoViagemController;
```

- [ ] **Step 5: Rodar o teste e confirmar que passa**

Run: `php artisan test tests/Feature/Localidade/PontoViagemApiTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/PontoViagemController.php routes/api.php tests/Feature/Localidade/PontoViagemApiTest.php
git commit -m "feat: adiciona endpoint agregador de unidades e localidades para origem/destino

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01YFrj2A7MTMbtfxyPbtxvYb"
```

---

## Task 4: Colunas polimórficas `origem`/`destino` em `solicitacoes`

**Files:**
- Create: `database/migrations/2026_09_09_000002_add_origem_destino_polimorfico_to_solicitacoes_table.php`
- Modify: `app/Models/Solicitacao.php`
- Modify: `app/Http/Resources/SolicitacaoResource.php`
- Modify: `app/Http/Controllers/Api/SolicitacaoController.php`
- Modify: `app/Http/Requests/Solicitacao/StoreSolicitacaoRequest.php`
- Modify: `tests/Feature/Solicitacao/SolicitacaoApiTest.php:29-46`
- Modify: `database/factories/SolicitacaoFactory.php` (se existir campo `origem_unidade_id`/`destino_unidade_id`)

**Interfaces:**
- Consumes: `App\Models\Localidade` (Task 1), `App\Models\Unidade`.
- Produces: `Solicitacao::origem()` e `Solicitacao::destino()` — métodos que retornam `Unidade|Localidade|null` resolvendo por `origem_tipo`/`origem_id` (e `destino_tipo`/`destino_id`). Colunas de banco: `origem_tipo` (string nullable), `origem_id` (uuid nullable), `destino_tipo` (string nullable), `destino_id` (uuid nullable). Requisição API passa a exigir `origem_tipo`/`origem_id`/`destino_tipo`/`destino_id` em vez de `origem_unidade_id`/`destino_unidade_id`.

- [ ] **Step 1: Verificar se existe `SolicitacaoFactory` com os campos antigos**

Run: `grep -rn "origem_unidade_id\|destino_unidade_id" database/factories/`

Se existir referência em `database/factories/SolicitacaoFactory.php`, anotar para ajustar no Step 6.

- [ ] **Step 2: Escrever/ajustar o teste (falha esperada — API ainda usa campos antigos)**

Editar `tests/Feature/Solicitacao/SolicitacaoApiTest.php`, substituindo o teste `test_transferencia_paciente_exige_origem_destino_e_atendimento` (linhas 29-46) por:

```php
    public function test_transferencia_paciente_exige_origem_destino_e_atendimento(): void
    {
        $this->loginOperador();

        $this->postJson('/api/solicitacoes', ['motivo' => 'transferencia_paciente'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['origem_tipo', 'origem_id', 'destino_tipo', 'destino_id', 'numero_atendimento']);

        $origem = Unidade::factory()->create();
        $destino = Unidade::factory()->create();

        $this->postJson('/api/solicitacoes', [
            'motivo' => 'transferencia_paciente',
            'origem_tipo' => 'unidade',
            'origem_id' => $origem->id,
            'destino_tipo' => 'unidade',
            'destino_id' => $destino->id,
            'numero_atendimento' => 12345,
        ])->assertCreated();
    }

    public function test_transferencia_paciente_aceita_localidade_como_origem_ou_destino(): void
    {
        $this->loginOperador();

        $origem = Unidade::factory()->create();
        $destino = Localidade::factory()->create();

        $response = $this->postJson('/api/solicitacoes', [
            'motivo' => 'transferencia_paciente',
            'origem_tipo' => 'unidade',
            'origem_id' => $origem->id,
            'destino_tipo' => 'localidade',
            'destino_id' => $destino->id,
            'numero_atendimento' => 54321,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('solicitacoes', [
            'destino_tipo' => 'localidade',
            'destino_id' => $destino->id,
        ]);
    }
```

Adicionar `use App\Models\Localidade;` no topo do arquivo de teste, junto aos demais `use App\Models\...`.

- [ ] **Step 3: Rodar os testes e confirmar que falham**

Run: `php artisan test tests/Feature/Solicitacao/SolicitacaoApiTest.php`
Expected: FAIL — `origem_tipo`/`origem_id` ainda não existem como colunas nem regras de validação.

- [ ] **Step 4: Criar a migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->string('origem_tipo')->nullable()->after('origem_unidade_id');
            $table->string('destino_tipo')->nullable()->after('destino_unidade_id');
        });

        DB::table('solicitacoes')->whereNotNull('origem_unidade_id')->update(['origem_tipo' => 'unidade']);
        DB::table('solicitacoes')->whereNotNull('destino_unidade_id')->update(['destino_tipo' => 'unidade']);

        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->dropForeign(['origem_unidade_id']);
            $table->dropForeign(['destino_unidade_id']);
            $table->renameColumn('origem_unidade_id', 'origem_id');
            $table->renameColumn('destino_unidade_id', 'destino_id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->renameColumn('origem_id', 'origem_unidade_id');
            $table->renameColumn('destino_id', 'destino_unidade_id');
            $table->dropColumn(['origem_tipo', 'destino_tipo']);
        });

        Schema::table('solicitacoes', function (Blueprint $table) {
            $table->foreign('origem_unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('destino_unidade_id')->references('id')->on('unidades')->nullOnDelete();
        });
    }
};
```

- [ ] **Step 5: Atualizar o model `Solicitacao`**

Em `app/Models/Solicitacao.php`, trocar o array `$fillable` (linhas 20-25):

```php
    protected $fillable = [
        'usuario_id', 'unidade_id', 'motivo',
        'origem_tipo', 'origem_id', 'destino_tipo', 'destino_id', 'numero_atendimento',
        'cidade', 'hospital_destino', 'fornecedor_nome',
        'status', 'viagem_id', 'motorista_pendente_id', 'veiculo_pendente_id', 'motivo_recusa', 'observacoes',
    ];
```

E trocar os métodos `origemUnidade()`/`destinoUnidade()` (linhas 43-57) por:

```php
    public function origem(): Unidade|Localidade|null
    {
        return $this->resolverPonto($this->origem_tipo, $this->origem_id);
    }

    public function destino(): Unidade|Localidade|null
    {
        return $this->resolverPonto($this->destino_tipo, $this->destino_id);
    }

    private function resolverPonto(?string $tipo, ?string $id): Unidade|Localidade|null
    {
        if (! $tipo || ! $id) {
            return null;
        }

        return match ($tipo) {
            'unidade' => Unidade::find($id),
            'localidade' => Localidade::find($id),
            default => null,
        };
    }
```

- [ ] **Step 6: Atualizar `SolicitacaoResource`**

Em `app/Http/Resources/SolicitacaoResource.php`, substituir as linhas:

```php
            'origem_unidade_id' => $this->origem_unidade_id,
            'destino_unidade_id' => $this->destino_unidade_id,
```

por:

```php
            'origem_tipo' => $this->origem_tipo,
            'origem_id' => $this->origem_id,
            'destino_tipo' => $this->destino_tipo,
            'destino_id' => $this->destino_id,
```

E substituir:

```php
            'origem' => $this->whenLoaded('origemUnidade', fn () => $this->origemUnidade?->nome),
            'destino' => $this->whenLoaded('destinoUnidade', fn () => $this->destinoUnidade?->nome),
```

por:

```php
            'origem' => $this->origem()?->nome,
            'destino' => $this->destino()?->nome,
```

- [ ] **Step 7: Atualizar `SolicitacaoController`**

Em `app/Http/Controllers/Api/SolicitacaoController.php`, remover `'origemUnidade', 'destinoUnidade'` de todas as chamadas `with([...])`/`load([...])` (linhas 25, 58, 86, 104, 122) — já que `origem()`/`destino()` agora são métodos, não relações Eloquent carregáveis via `with`/`load`. Cada ocorrência de:

```php
->with(['usuario', 'origemUnidade', 'destinoUnidade', 'viagem.motorista', 'viagem.veiculo', 'motoristaPendente', 'veiculoPendente'])
```

vira:

```php
->with(['usuario', 'viagem.motorista', 'viagem.veiculo', 'motoristaPendente', 'veiculoPendente'])
```

(mesma troca nas variantes com `->load([...])`).

- [ ] **Step 8: Atualizar `StoreSolicitacaoRequest`**

Em `app/Http/Requests/Solicitacao/StoreSolicitacaoRequest.php`, substituir as linhas:

```php
            'origem_unidade_id' => 'required_if:motivo,transferencia_paciente,transporte_colaborador|nullable|uuid|exists:unidades,id',
            'destino_unidade_id' => 'required_if:motivo,transferencia_paciente,transporte_colaborador|nullable|uuid|exists:unidades,id',
```

por:

```php
            'origem_tipo' => 'required_if:motivo,transferencia_paciente,transporte_colaborador|nullable|in:unidade,localidade',
            'origem_id' => ['required_if:motivo,transferencia_paciente,transporte_colaborador', 'nullable', 'uuid', new \App\Rules\PontoViagemExiste($this->input('origem_tipo'))],
            'destino_tipo' => 'required_if:motivo,transferencia_paciente,transporte_colaborador|nullable|in:unidade,localidade',
            'destino_id' => ['required_if:motivo,transferencia_paciente,transporte_colaborador', 'nullable', 'uuid', new \App\Rules\PontoViagemExiste($this->input('destino_tipo'))],
```

Criar a regra customizada `app/Rules/PontoViagemExiste.php` (necessária porque `exists:` do Laravel não suporta tabela dinâmica conforme o valor de outro campo):

```php
<?php

namespace App\Rules;

use App\Models\Localidade;
use App\Models\Unidade;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PontoViagemExiste implements ValidationRule
{
    public function __construct(private ?string $tipo) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->tipo || ! $value) {
            return;
        }

        $existe = match ($this->tipo) {
            'unidade' => Unidade::whereKey($value)->exists(),
            'localidade' => Localidade::whereKey($value)->exists(),
            default => false,
        };

        if (! $existe) {
            $fail("O :attribute selecionado não existe como {$this->tipo}.");
        }
    }
}
```

- [ ] **Step 9: Ajustar `SolicitacaoFactory` se necessário**

Se o Step 1 encontrou `origem_unidade_id`/`destino_unidade_id` em `database/factories/SolicitacaoFactory.php`, trocar para `origem_tipo => 'unidade'`, `origem_id => Unidade::factory()`, `destino_tipo => 'unidade'`, `destino_id => Unidade::factory()`.

- [ ] **Step 10: Rodar a migration e os testes, confirmar que passam**

Run: `php artisan migrate && php artisan test tests/Feature/Solicitacao/`
Expected: PASS (todos os testes de `SolicitacaoApiTest`, incluindo os dois de origem/destino).

- [ ] **Step 11: Rodar a suíte completa para checar regressões**

Run: `php artisan test`
Expected: PASS — nenhum teste fora de `Solicitacao` deve quebrar (a única tabela alterada é `solicitacoes`).

- [ ] **Step 12: Commit**

```bash
git add database/migrations/2026_09_09_000002_add_origem_destino_polimorfico_to_solicitacoes_table.php app/Models/Solicitacao.php app/Http/Resources/SolicitacaoResource.php app/Http/Controllers/Api/SolicitacaoController.php app/Http/Requests/Solicitacao/StoreSolicitacaoRequest.php app/Rules/PontoViagemExiste.php tests/Feature/Solicitacao/SolicitacaoApiTest.php database/factories/SolicitacaoFactory.php
git commit -m "feat: torna origem/destino de solicitacoes polimorficos (unidade ou localidade)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01YFrj2A7MTMbtfxyPbtxvYb"
```

---

## Task 5: Tela admin de Cadastro de Localidades (`resources/js`)

**Files:**
- Create: `resources/js/api/localidades.js`
- Create: `resources/js/pages/localidades/LocalidadesList.jsx`
- Create: `resources/js/pages/localidades/LocalidadeForm.jsx`
- Modify: `resources/js/FleetApp.jsx`
- Modify: `resources/js/pages/configuracoes/ConfiguracoesHub.jsx`

**Interfaces:**
- Consumes: `GET/POST /api/localidades`, `PATCH/DELETE /api/localidades/{id}` (Task 2).
- Produces: rota `/configuracoes/localidades` (admin-only), acessível a partir do hub de Configurações (`ConfiguracoesHub.jsx`), que já existe no branch e hoje lista apenas a seção "LDAP por Unidade".

- [ ] **Step 1: Criar o API client**

```js
import api from './axios'

export const listar = (params) => api.get('/localidades', { params })
export const buscar = (id) => api.get(`/localidades/${id}`)
export const criar = (data) => api.post('/localidades', data)
export const atualizar = (id, data) => api.patch(`/localidades/${id}`, data)
export const desativar = (id) => api.delete(`/localidades/${id}`)
```

Salvar em `resources/js/api/localidades.js`.

- [ ] **Step 2: Criar o formulário**

```jsx
import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import * as localidadesApi from '../../api/localidades'
import Alert from '../../components/ui/Alert'

export default function LocalidadeForm({ localidade, onSuccess }) {
  const qc = useQueryClient()
  const [form, setForm] = useState({
    nome: localidade?.nome ?? '',
    endereco: localidade?.endereco ?? '',
    latitude: localidade?.latitude ?? '',
    longitude: localidade?.longitude ?? '',
    telefone: localidade?.telefone ?? '',
    email: localidade?.email ?? '',
    ativo: localidade?.ativo ?? true,
  })
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})

  const set = (k) => (e) =>
    setForm(f => ({ ...f, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }))

  const salvar = useMutation({
    mutationFn: (data) =>
      localidade ? localidadesApi.atualizar(localidade.id, data) : localidadesApi.criar(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['localidades'] })
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
    salvar.mutate(form)
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
        <input
          type="text"
          required
          value={form.nome}
          onChange={set('nome')}
          maxLength={150}
          placeholder="Ex: Clínica Parceira ABC"
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        {fieldErrors.nome && <p className="text-red-500 text-xs mt-1">{fieldErrors.nome[0]}</p>}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Endereço</label>
        <input
          type="text"
          value={form.endereco}
          onChange={set('endereco')}
          maxLength={255}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        {fieldErrors.endereco && <p className="text-red-500 text-xs mt-1">{fieldErrors.endereco[0]}</p>}
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
          <input
            type="number"
            step="any"
            value={form.latitude}
            onChange={set('latitude')}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          {fieldErrors.latitude && <p className="text-red-500 text-xs mt-1">{fieldErrors.latitude[0]}</p>}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
          <input
            type="number"
            step="any"
            value={form.longitude}
            onChange={set('longitude')}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          {fieldErrors.longitude && <p className="text-red-500 text-xs mt-1">{fieldErrors.longitude[0]}</p>}
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
          <input
            type="text"
            value={form.telefone}
            onChange={set('telefone')}
            maxLength={20}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          {fieldErrors.telefone && <p className="text-red-500 text-xs mt-1">{fieldErrors.telefone[0]}</p>}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
          <input
            type="email"
            value={form.email}
            onChange={set('email')}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          {fieldErrors.email && <p className="text-red-500 text-xs mt-1">{fieldErrors.email[0]}</p>}
        </div>
      </div>

      {localidade && (
        <div className="flex items-center gap-2">
          <input
            type="checkbox"
            id="ativo"
            checked={form.ativo}
            onChange={set('ativo')}
            className="rounded"
          />
          <label htmlFor="ativo" className="text-sm text-gray-700">Localidade ativa</label>
        </div>
      )}

      <div className="flex justify-end pt-2">
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

Salvar em `resources/js/pages/localidades/LocalidadeForm.jsx`.

- [ ] **Step 3: Criar a lista**

```jsx
import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Pencil, MapPin } from 'lucide-react'
import * as localidadesApi from '../../api/localidades'
import Modal from '../../components/ui/Modal'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Alert from '../../components/ui/Alert'
import LocalidadeForm from './LocalidadeForm'

export default function LocalidadesList() {
  const qc = useQueryClient()
  const [formOpen, setFormOpen] = useState(false)
  const [editTarget, setEditTarget] = useState(null)
  const [error, setError] = useState('')

  const { data, isLoading } = useQuery({
    queryKey: ['localidades'],
    queryFn: () => localidadesApi.listar().then(r => r.data),
  })

  const desativar = useMutation({
    mutationFn: (id) => localidadesApi.desativar(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['localidades'] }),
    onError: (e) => setError(e.response?.data?.message ?? 'Erro ao desativar'),
  })

  const reativar = useMutation({
    mutationFn: (id) => localidadesApi.atualizar(id, { ativo: true }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['localidades'] }),
    onError: (e) => setError(e.response?.data?.message ?? 'Erro ao reativar'),
  })

  const openEdit = (l) => {
    setEditTarget(l)
    setFormOpen(true)
  }

  const closeForm = () => {
    setFormOpen(false)
    setEditTarget(null)
  }

  if (isLoading) return <LoadingSpinner />

  const lista = data ?? []

  return (
    <div className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div className="flex justify-end">
        <button
          onClick={() => setFormOpen(true)}
          className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors"
        >
          <Plus size={16} /> Nova localidade
        </button>
      </div>

      {lista.length === 0 && (
        <div className="bg-white rounded-xl border border-gray-200 px-6 py-12 text-center text-gray-400">
          <MapPin size={36} className="mx-auto mb-3 opacity-30" />
          <p>Nenhuma localidade cadastrada</p>
        </div>
      )}

      {lista.length > 0 && (
        <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100 overflow-hidden">
          {lista.map(l => (
            <div
              key={l.id}
              className={`flex items-center justify-between px-5 py-4 ${!l.ativo ? 'opacity-60' : ''}`}
            >
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 rounded-lg flex items-center justify-center bg-blue-100">
                  <MapPin size={18} className="text-blue-600" />
                </div>
                <div>
                  <p className="text-sm font-medium text-gray-800">{l.nome}</p>
                  <p className="text-xs text-gray-500">{l.endereco || 'Sem endereço cadastrado'}</p>
                </div>
              </div>

              <div className="flex items-center gap-2">
                <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                  l.ativo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                }`}>
                  {l.ativo ? 'Ativa' : 'Inativa'}
                </span>
                <button
                  onClick={() => openEdit(l)}
                  title="Editar"
                  className="text-gray-400 hover:text-blue-600 p-1.5 rounded-lg hover:bg-blue-50 transition-colors"
                >
                  <Pencil size={15} />
                </button>
                <button
                  onClick={() => l.ativo ? desativar.mutate(l.id) : reativar.mutate(l.id)}
                  disabled={desativar.isPending || reativar.isPending}
                  title={l.ativo ? 'Inativar' : 'Reativar'}
                  className={`text-xs px-2.5 py-1 rounded-lg font-medium transition-colors ${
                    l.ativo ? 'text-red-500 hover:bg-red-50' : 'text-green-600 hover:bg-green-50'
                  }`}
                >
                  {l.ativo ? 'Inativar' : 'Reativar'}
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      <Modal
        open={formOpen}
        onClose={closeForm}
        title={editTarget ? 'Editar localidade' : 'Nova localidade'}
      >
        <LocalidadeForm localidade={editTarget} onSuccess={closeForm} />
      </Modal>
    </div>
  )
}
```

Salvar em `resources/js/pages/localidades/LocalidadesList.jsx`.

- [ ] **Step 4: Registrar a rota em `FleetApp.jsx`**

Adicionar o import junto aos demais (após `import ConfiguracoesLdap from './pages/configuracoes/ConfiguracoesLdap'`):

```js
import LocalidadesList from './pages/localidades/LocalidadesList'
```

E adicionar a rota, junto à rota `/configuracoes/ldap` já existente:

```jsx
              <Route path="/configuracoes/localidades" element={
                <AdminRoute><LocalidadesList /></AdminRoute>
              } />
```

- [ ] **Step 5: Adicionar a seção ao hub de Configurações**

Em `resources/js/pages/configuracoes/ConfiguracoesHub.jsx`, adicionar `MapPin` ao import de ícones do `lucide-react` (junto a `ShieldCheck`, `ChevronRight`) e adicionar um item ao array `secoes` (junto ao item `to: '/configuracoes/ldap'`):

```js
  {
    to: '/configuracoes/localidades',
    titulo: 'Localidades',
    descricao: 'Cadastre locais externos (hospitais parceiros, clínicas, empresas) para usar como origem/destino nas solicitações de transporte.',
    icon: MapPin,
  },
```

- [ ] **Step 6: Testar manualmente no navegador**

Rodar `composer dev`, logar como admin, navegar até `/configuracoes` → "Localidades", criar uma localidade, editar, inativar e reativar. Confirmar que os dados persistem após reload.

- [ ] **Step 7: Commit**

```bash
git add resources/js/api/localidades.js resources/js/pages/localidades resources/js/FleetApp.jsx resources/js/pages/configuracoes/ConfiguracoesHub.jsx
git commit -m "feat: adiciona tela admin de cadastro de localidades ao hub de configuracoes

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01YFrj2A7MTMbtfxyPbtxvYb"
```

---

## Task 6: Integrar Origem/Destino combinados na Nova Solicitação (`resources/solicitacao-js`)

**Files:**
- Create: `resources/solicitacao-js/api/pontosViagem.js`
- Modify: `resources/solicitacao-js/pages/NovaSolicitacao.jsx`

**Interfaces:**
- Consumes: `GET /api/pontos-viagem` (Task 3) → `[{ tipo, id, nome }]`.
- Produces: payload de `POST /api/solicitacoes` com `origem_tipo`/`origem_id`/`destino_tipo`/`destino_id` em vez de `origem_unidade_id`/`destino_unidade_id`.

- [ ] **Step 1: Criar o API client**

```js
import api from './axios'

export const listar = () => api.get('/pontos-viagem')
```

Salvar em `resources/solicitacao-js/api/pontosViagem.js`.

- [ ] **Step 2: Ajustar `NovaSolicitacao.jsx` — import e estado inicial**

Trocar a linha 4:

```js
import * as unidadesApi from '../api/unidades'
```

por:

```js
import * as pontosViagemApi from '../api/pontosViagem'
```

Trocar o `INITIAL` (linhas 16-25):

```js
const INITIAL = {
  motivo: '',
  origem_tipo: '',
  origem_id: '',
  destino_tipo: '',
  destino_id: '',
  numero_atendimento: '',
  cidade: '',
  hospital_destino: '',
  fornecedor_nome: '',
  observacoes: '',
}
```

- [ ] **Step 3: Ajustar o `useEffect` e o state de opções**

Trocar as linhas 30 e 35-37:

```js
  const [pontos, setPontos] = useState([])
```

```js
  useEffect(() => {
    pontosViagemApi.listar().then(({ data }) => setPontos(data.data ?? data)).catch(() => {})
  }, [])
```

- [ ] **Step 4: Ajustar `setField` para gravar tipo + id ao selecionar um ponto**

Adicionar, logo após a definição de `setField` (linha 39), uma função dedicada para os selects de origem/destino, já que cada `<option>` precisa gravar tanto o id quanto o tipo (`unidade`/`localidade`) do item escolhido:

```js
  const setPonto = (prefixo, pontoId) => {
    const ponto = pontos.find(p => p.id === pontoId)
    setForm(f => ({ ...f, [`${prefixo}_id`]: pontoId, [`${prefixo}_tipo`]: ponto?.tipo ?? '' }))
  }
```

- [ ] **Step 5: Ajustar os selects de Origem e Destino**

Substituir o bloco `{precisaOrigemDestino && (...)}` (linhas 100-129) por:

```jsx
        {precisaOrigemDestino && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Origem *</label>
              <select
                required
                value={form.origem_id}
                onChange={(e) => setPonto('origem', e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
              >
                <option value="">Selecione...</option>
                {pontos.map(p => <option key={`${p.tipo}-${p.id}`} value={p.id}>{p.nome}</option>)}
              </select>
              {fe('origem_id')}
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Destino *</label>
              <select
                required
                value={form.destino_id}
                onChange={(e) => setPonto('destino', e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
              >
                <option value="">Selecione...</option>
                {pontos.map(p => <option key={`${p.tipo}-${p.id}`} value={p.id}>{p.nome}</option>)}
              </select>
              {fe('destino_id')}
            </div>
          </div>
        )}
```

- [ ] **Step 6: Testar manualmente no navegador**

Rodar `composer dev`, logar como operador, ir em "Nova Solicitação" → "Transferência de Paciente". Confirmar que os selects de Origem/Destino mostram unidades e localidades juntas, e que o envio funciona (checar via Network que o payload traz `origem_tipo`/`origem_id`/`destino_tipo`/`destino_id`).

- [ ] **Step 7: Commit**

```bash
git add resources/solicitacao-js/api/pontosViagem.js resources/solicitacao-js/pages/NovaSolicitacao.jsx
git commit -m "feat: combina unidades e localidades nos selects de origem/destino da solicitacao

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01YFrj2A7MTMbtfxyPbtxvYb"
```

---

## Task 7: Suíte completa e verificação final

**Files:** nenhum arquivo novo — apenas verificação.

- [ ] **Step 1: Rodar toda a suíte de testes PHP**

Run: `php artisan test`
Expected: PASS — 0 failures.

- [ ] **Step 2: Rodar o Pint**

Run: `./vendor/bin/pint --test`
Expected: sem violações de estilo nos arquivos novos/alterados. Se houver, rodar `./vendor/bin/pint` (sem `--test`) para corrigir e comitar separadamente.

- [ ] **Step 3: Testar o fluxo completo end-to-end no navegador**

Com `composer dev` rodando: logar como admin, cadastrar uma Localidade em `/localidades`; logar como operador, abrir Nova Solicitação, escolher "Transporte de Colaborador(es)", confirmar que a Localidade cadastrada aparece nas opções de Origem/Destino junto com as Unidades, e que a solicitação é criada com sucesso.

- [ ] **Step 4: Commit final (se Pint tiver corrigido algo)**

```bash
git add -A
git commit -m "style: aplica correcoes de Pint na feature de localidades

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01YFrj2A7MTMbtfxyPbtxvYb"
```
