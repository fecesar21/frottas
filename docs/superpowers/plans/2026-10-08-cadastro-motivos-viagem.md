# Cadastro de Motivos de Viagem — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Motivos de viagem passam a ser cadastrados pelo admin em Configurações (tipo de veículo, disponível em solicitações, ativo), substituindo as listas fixas no código.

**Architecture:** Tabela `motivos_viagem` cujo `codigo` (texto) continua sendo o valor gravado em `viagens.motivo_viagem` e `solicitacoes.motivo`. O model `MotivoViagem` concentra rótulo, compatibilidade com veículo e geração de código; validações, relatórios, notificações, roteamento e as duas SPAs passam a consultá-lo.

**Tech Stack:** Laravel 11 (PHPUnit, SQLite em teste), React + React Query + Vitest.

**Spec:** `docs/superpowers/specs/2026-10-08-cadastro-motivos-viagem-design.md`

## Global Constraints

- Models com UUID (`HasUuids`, `$incrementing = false`, `$keyType = 'string'`).
- `tipo_veiculo` ∈ `administrativo` | `ambulancia` | `ambos`.
- `codigo` imutável após criação; gerado com `Str::slug($nome, '_')`, sufixo `_2`, `_3`… se colidir.
- Motivos `sistema`: renomear/inativar sim; excluir ou mudar `tipo_veiculo` não (422).
- Ambulância = `Veiculo::ehAmbulancia()` (modelo contém "AMBULANCIA", sem acento/caixa).
- Mensagens de erro de compatibilidade inalteradas: "Motivo não permitido para ambulância." / "Motivo não permitido para veículo administrativo."
- Textos de UI em português com acentuação correta.
- Migration exige backup MySQL antes do deploy (memória `producao_acesso`).

## Review Focus

1. Edição de viagem antiga cujo motivo foi inativado, sem trocar o motivo → deve continuar salvando (teste na Task 3).
2. Nome que gera código já existente (ex.: "Alimentação" → `alimentacao`) → recebe `alimentacao_2`, não 500 (teste na Task 2).
3. Viagem/solicitação com código que não existe mais na tabela (dado legado) → relatório mostra fallback `Str::title`, não quebra (teste na Task 1).
4. Operador (não admin) tentando POST/PUT/DELETE → 403; GET continua funcionando para operador e solicitante (teste na Task 2).
5. Motivo `ambos` em ambulância e em administrativo → aceito nos dois (teste na Task 3) e roteado para ambos os tipos (teste na Task 5).

---

### Task 1: Model, migration com seed e helpers

**Files:**
- Create: `database/migrations/2026_10_08_000001_create_motivos_viagem_table.php`
- Create: `app/Models/MotivoViagem.php`
- Create: `database/factories/MotivoViagemFactory.php`
- Modify: `app/Models/Viagem.php` (remover `ROTULOS_MOTIVO`; `rotuloMotivo` delega)
- Test: `tests/Feature/MotivoViagem/MotivoViagemModelTest.php`

**Interfaces — Produces:**
- `MotivoViagem::rotulo(?string $codigo): string`
- `MotivoViagem::porCodigo(?string $codigo): ?MotivoViagem` (cache estático por requisição; `MotivoViagem::limparCache()` chamado nos eventos `saved`/`deleted`)
- `MotivoViagem::gerarCodigo(string $nome): string`
- `$motivo->permiteVeiculo(?Veiculo $v): bool`
- `$motivo->emUso(): bool`
- scopes `ativos()`, `paraSolicitacao()`
- constantes `TIPO_ADMINISTRATIVO`, `TIPO_AMBULANCIA`, `TIPO_AMBOS`

- [ ] **Step 1: Teste falhando**

```php
<?php

namespace Tests\Feature\MotivoViagem;

use App\Models\MotivoViagem;
use App\Models\Veiculo;
use App\Models\Viagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotivoViagemModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_semeia_os_8_motivos_de_sistema(): void
    {
        $this->assertSame(8, MotivoViagem::where('sistema', true)->count());
        $this->assertSame('ambulancia', MotivoViagem::porCodigo('tfd')->tipo_veiculo);
        $this->assertFalse(MotivoViagem::porCodigo('alimentacao')->disponivel_solicitacao);
        $this->assertTrue(MotivoViagem::porCodigo('buscar_medico')->disponivel_solicitacao);
    }

    public function test_rotulo_usa_nome_cadastrado_e_fallbacks(): void
    {
        $this->assertSame('Serviços Administrativos Diversos', MotivoViagem::rotulo('servicos_administrativos'));
        $this->assertSame('Não informado', MotivoViagem::rotulo(null));
        $this->assertSame('Motivo Legado', MotivoViagem::rotulo('motivo_legado'));
        $this->assertSame('TFD', Viagem::rotuloMotivo('tfd'));
    }

    public function test_permite_veiculo_por_tipo(): void
    {
        $amb = Veiculo::factory()->make(['modelo' => 'AMBULÂNCIA X']);
        $adm = Veiculo::factory()->make(['modelo' => 'STRADA']);
        $ambos = MotivoViagem::factory()->create(['tipo_veiculo' => 'ambos']);

        $this->assertTrue(MotivoViagem::porCodigo('tfd')->permiteVeiculo($amb));
        $this->assertFalse(MotivoViagem::porCodigo('tfd')->permiteVeiculo($adm));
        $this->assertTrue($ambos->permiteVeiculo($amb));
        $this->assertTrue($ambos->permiteVeiculo($adm));
    }

    public function test_gerar_codigo_evita_colisao(): void
    {
        $this->assertSame('alimentacao_2', MotivoViagem::gerarCodigo('Alimentação'));
        $this->assertSame('lavar_veiculo', MotivoViagem::gerarCodigo('Lavar Veículo'));
    }
}
```

- [ ] **Step 2:** `php artisan test tests/Feature/MotivoViagem/MotivoViagemModelTest.php` → FAIL (classe inexistente).

- [ ] **Step 3: Migration**

```php
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
        Schema::create('motivos_viagem', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 80)->unique();
            $table->string('nome', 120)->unique();
            $table->enum('tipo_veiculo', ['administrativo', 'ambulancia', 'ambos']);
            $table->boolean('disponivel_solicitacao')->default(false);
            $table->boolean('ativo')->default(true);
            $table->boolean('sistema')->default(false);
            $table->timestamps();
        });

        $agora = now();
        $motivos = [
            ['transferencia_paciente', 'Transferência de Paciente', 'ambulancia', true],
            ['tfd', 'TFD', 'ambulancia', true],
            ['buscar_medico', 'Buscar Médico em Outra Cidade', 'administrativo', true],
            ['material_outro_hospital', 'Levar Material em Outro Hospital', 'administrativo', true],
            ['transporte_colaborador', 'Transporte de Colaborador(es)', 'administrativo', true],
            ['buscar_material_fornecedor', 'Buscar Materiais em Fornecedor', 'administrativo', true],
            ['alimentacao', 'Alimentação (Levar/Buscar)', 'administrativo', false],
            ['servicos_administrativos', 'Serviços Administrativos Diversos', 'administrativo', false],
        ];

        DB::table('motivos_viagem')->insert(array_map(fn ($m) => [
            'id' => (string) Str::uuid(), 'codigo' => $m[0], 'nome' => $m[1], 'tipo_veiculo' => $m[2],
            'disponivel_solicitacao' => $m[3], 'ativo' => true, 'sistema' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ], $motivos));
    }

    public function down(): void
    {
        Schema::dropIfExists('motivos_viagem');
    }
};
```

- [ ] **Step 4: Model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MotivoViagem extends Model
{
    use HasFactory, HasUuids;

    public const TIPO_ADMINISTRATIVO = 'administrativo';
    public const TIPO_AMBULANCIA = 'ambulancia';
    public const TIPO_AMBOS = 'ambos';

    protected $table = 'motivos_viagem';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['codigo', 'nome', 'tipo_veiculo', 'disponivel_solicitacao', 'ativo'];

    protected $attributes = ['ativo' => true, 'disponivel_solicitacao' => false, 'sistema' => false];

    protected $casts = ['disponivel_solicitacao' => 'boolean', 'ativo' => 'boolean', 'sistema' => 'boolean'];

    /** @var array<string, ?MotivoViagem>|null */
    private static ?array $cache = null;

    protected static function booted(): void
    {
        static::saved(fn () => self::limparCache());
        static::deleted(fn () => self::limparCache());
    }

    public static function limparCache(): void
    {
        self::$cache = null;
    }

    public static function porCodigo(?string $codigo): ?self
    {
        self::$cache ??= self::all()->keyBy('codigo')->all();

        return self::$cache[trim((string) $codigo)] ?? null;
    }

    public static function rotulo(?string $codigo): string
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return 'Não informado';
        }

        return self::porCodigo($codigo)?->nome ?? Str::title(str_replace('_', ' ', $codigo));
    }

    public static function gerarCodigo(string $nome): string
    {
        $base = Str::limit(Str::slug($nome, '_'), 70, '') ?: 'motivo';
        $codigo = $base;
        for ($i = 2; self::where('codigo', $codigo)->exists(); $i++) {
            $codigo = "{$base}_{$i}";
        }

        return $codigo;
    }

    public function permiteVeiculo(?Veiculo $veiculo): bool
    {
        if (! $veiculo || $this->tipo_veiculo === self::TIPO_AMBOS) {
            return true;
        }

        return $veiculo->ehAmbulancia() === ($this->tipo_veiculo === self::TIPO_AMBULANCIA);
    }

    public function emUso(): bool
    {
        return DB::table('viagens')->where('motivo_viagem', $this->codigo)->exists()
            || DB::table('solicitacoes')->where('motivo', $this->codigo)->exists();
    }

    public function scopeAtivos(Builder $q): Builder
    {
        return $q->where('ativo', true);
    }

    public function scopeParaSolicitacao(Builder $q): Builder
    {
        return $q->where('disponivel_solicitacao', true);
    }
}
```

Factory:

```php
<?php

namespace Database\Factories;

use App\Models\MotivoViagem;
use Illuminate\Database\Eloquent\Factories\Factory;

class MotivoViagemFactory extends Factory
{
    protected $model = MotivoViagem::class;

    public function definition(): array
    {
        $nome = 'Motivo '.$this->faker->unique()->words(3, true);

        return [
            'codigo' => \Illuminate\Support\Str::slug($nome, '_'),
            'nome' => $nome,
            'tipo_veiculo' => 'administrativo',
            'disponivel_solicitacao' => false,
            'ativo' => true,
        ];
    }
}
```

- [ ] **Step 5:** Em `app/Models/Viagem.php`, apagar `ROTULOS_MOTIVO` e trocar o corpo de `rotuloMotivo` por `return MotivoViagem::rotulo($motivo);` (manter assinatura; ajustar `use`). Rodar `grep -rn "ROTULOS_MOTIVO" app resources tests` — deve sair vazio.

- [ ] **Step 6:** Rodar o teste da task + `php artisan test` completo → PASS.

- [ ] **Step 7: Commit** — `feat(motivos): tabela motivos_viagem com seed e helpers`

---

### Task 2: API de cadastro

**Files:**
- Create: `app/Http/Controllers/Api/MotivoViagemController.php`
- Create: `app/Http/Resources/MotivoViagemResource.php`
- Modify: `routes/api.php` (GET no grupo autenticado, perto de Localidades; POST/PUT/DELETE dentro do grupo `admin`)
- Test: `tests/Feature/MotivoViagem/MotivoViagemApiTest.php`

**Interfaces — Consumes:** Task 1. **Produces:** `GET/POST /api/motivos-viagem`, `PUT/DELETE /api/motivos-viagem/{motivoViagem}`; JSON `{data: [{id, codigo, nome, tipo_veiculo, disponivel_solicitacao, ativo, sistema, em_uso?}]}`.

- [ ] **Step 1: Testes falhando** (seguir o padrão de autenticação de `tests/Feature/Viagem/ViagemApiTest.php` — `Sanctum::actingAs` com `Usuario::factory()` de perfil `admin`/`operador`):

```php
public function test_lista_ativos_para_qualquer_usuario_e_todos_so_para_admin(): void
{
    MotivoViagem::porCodigo('tfd')->update(['ativo' => false]);
    Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'operador']));
    $this->getJson('/api/motivos-viagem')->assertOk()->assertJsonCount(7, 'data');
    $this->getJson('/api/motivos-viagem?solicitacao=1')->assertJsonCount(5, 'data');
    $this->getJson('/api/motivos-viagem?todos=1')->assertJsonCount(7, 'data'); // ignorado p/ não-admin

    Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'admin']));
    $this->getJson('/api/motivos-viagem?todos=1')->assertJsonCount(8, 'data')
        ->assertJsonPath('data.0.em_uso', false);
}

public function test_operador_nao_altera(): void
{
    Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'operador']));
    $this->postJson('/api/motivos-viagem', ['nome' => 'X', 'tipo_veiculo' => 'ambos'])->assertForbidden();
}

public function test_admin_cria_com_codigo_gerado_e_sem_colisao(): void
{
    Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'admin']));
    $this->postJson('/api/motivos-viagem', ['nome' => 'Alimentação', 'tipo_veiculo' => 'ambos'])
        ->assertJsonValidationErrors(['nome']); // nome duplicado
    $this->postJson('/api/motivos-viagem', ['nome' => 'Alimentacao', 'tipo_veiculo' => 'ambos', 'disponivel_solicitacao' => true])
        ->assertCreated()->assertJsonPath('data.codigo', 'alimentacao_2')->assertJsonPath('data.sistema', false);
}

public function test_sistema_nao_muda_tipo_nem_exclui_mas_renomeia_e_inativa(): void
{
    Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'admin']));
    $tfd = MotivoViagem::porCodigo('tfd');
    $this->putJson("/api/motivos-viagem/{$tfd->id}", ['tipo_veiculo' => 'ambos'])->assertJsonValidationErrors(['tipo_veiculo']);
    $this->putJson("/api/motivos-viagem/{$tfd->id}", ['nome' => 'TFD (Fora do Domicílio)', 'ativo' => false])->assertOk();
    $this->deleteJson("/api/motivos-viagem/{$tfd->id}")->assertStatus(422);
}

public function test_exclui_so_quando_nao_usado(): void
{
    Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'admin']));
    $usado = MotivoViagem::factory()->create();
    $livre = MotivoViagem::factory()->create();
    Viagem::factory()->create(['motivo_viagem' => $usado->codigo]);
    $this->deleteJson("/api/motivos-viagem/{$usado->id}")->assertStatus(422);
    $this->deleteJson("/api/motivos-viagem/{$livre->id}")->assertOk();
    $this->assertModelMissing($livre);
}
```

(Se `Viagem::factory()` não existir, criar a viagem com `Viagem::create([...])` como em `ViagemApiTest`.)

- [ ] **Step 2:** Rodar → FAIL (404).

- [ ] **Step 3: Controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MotivoViagemResource;
use App\Models\MotivoViagem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MotivoViagemController extends Controller
{
    public function index(Request $r): AnonymousResourceCollection
    {
        $todos = $r->boolean('todos') && $r->user()->perfil === 'admin';
        $q = MotivoViagem::query()->orderBy('nome');
        if (! $todos) {
            $q->ativos();
        }
        if ($r->boolean('solicitacao')) {
            $q->paraSolicitacao();
        }
        $motivos = $q->get();
        if ($todos) {
            $motivos->each(fn ($m) => $m->setAttribute('em_uso', $m->emUso()));
        }

        return MotivoViagemResource::collection($motivos);
    }

    public function store(Request $r): JsonResponse
    {
        $data = $r->validate($this->regras());
        $data['codigo'] = MotivoViagem::gerarCodigo($data['nome']);

        return (new MotivoViagemResource(MotivoViagem::create($data)))->response()->setStatusCode(201);
    }

    public function update(Request $r, MotivoViagem $motivoViagem): MotivoViagemResource
    {
        $data = $r->validate($this->regras($motivoViagem));
        if ($motivoViagem->sistema && isset($data['tipo_veiculo']) && $data['tipo_veiculo'] !== $motivoViagem->tipo_veiculo) {
            throw ValidationException::withMessages(['tipo_veiculo' => 'O tipo de veículo de um motivo de sistema não pode ser alterado.']);
        }
        $motivoViagem->update($data);

        return new MotivoViagemResource($motivoViagem);
    }

    public function destroy(MotivoViagem $motivoViagem): JsonResponse
    {
        abort_if($motivoViagem->sistema, 422, 'Motivos de sistema não podem ser excluídos. Inative-o.');
        abort_if($motivoViagem->emUso(), 422, 'Motivo já usado em viagens ou solicitações. Inative-o em vez de excluir.');
        $motivoViagem->delete();

        return response()->json(['message' => 'Motivo excluído.']);
    }

    private function regras(?MotivoViagem $atual = null): array
    {
        $req = $atual ? 'sometimes' : 'required';

        return [
            'nome' => [$req, 'string', 'max:120', Rule::unique('motivos_viagem', 'nome')->ignore($atual?->id)],
            'tipo_veiculo' => [$req, Rule::in(['administrativo', 'ambulancia', 'ambos'])],
            'disponivel_solicitacao' => 'sometimes|boolean',
            'ativo' => 'sometimes|boolean',
        ];
    }
}
```

Resource:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MotivoViagemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nome' => $this->nome,
            'tipo_veiculo' => $this->tipo_veiculo,
            'disponivel_solicitacao' => $this->disponivel_solicitacao,
            'ativo' => $this->ativo,
            'sistema' => $this->sistema,
            'em_uso' => $this->when(array_key_exists('em_uso', $this->resource->getAttributes()), fn () => (bool) $this->em_uso),
        ];
    }
}
```

Rotas (`routes/api.php`): no grupo autenticado, após Localidades:
```php
    // Motivos de viagem
    Route::get('motivos-viagem', [MotivoViagemController::class, 'index']);
```
dentro de `Route::middleware('admin')->group(...)`:
```php
        Route::post('motivos-viagem', [MotivoViagemController::class, 'store']);
        Route::put('motivos-viagem/{motivoViagem}', [MotivoViagemController::class, 'update']);
        Route::delete('motivos-viagem/{motivoViagem}', [MotivoViagemController::class, 'destroy']);
```
Conferir se o middleware `solicitante.restrito` deixa o solicitante acessar `GET motivos-viagem` (ler `app/Http/Middleware` correspondente; se for allowlist de rotas, incluir esta). Adicionar teste com perfil `solicitante` acessando o GET.

- [ ] **Step 4:** Rodar testes → PASS. `./vendor/bin/pint`.
- [ ] **Step 5: Commit** — `feat(motivos): API de cadastro de motivos de viagem`

---

### Task 3: Validação de viagens e solicitações

**Files:**
- Create: `app/Rules/MotivoViagemValido.php`
- Modify: `app/Http/Requests/Viagem/StoreViagemRequest.php`, `UpdateViagemRequest.php`, `app/Http/Requests/Solicitacao/StoreSolicitacaoRequest.php`
- Modify: `app/Models/Veiculo.php` (remover `MOTIVOS_SOMENTE_*` e `permiteMotivoViagem`)
- Test: `tests/Feature/Viagem/ViagemApiTest.php`, teste de solicitação existente em `tests/Feature/Solicitacao/` (localizar com `grep -rln "api/solicitacoes" tests`)

**Interfaces — Consumes:** `MotivoViagem::porCodigo`, `permiteVeiculo`. **Produces:** `new MotivoViagemValido(bool $solicitacao = false, ?string $aceitarInativo = null)`.

- [ ] **Step 1: Testes falhando** (em `ViagemApiTest`, reaproveitando `$payload`/veículos do teste de compatibilidade existente):

```php
public function test_motivo_inativo_e_recusado_e_ambos_aceito_nos_dois_tipos(): void
{
    // ... mesmo setup de $strada, $ambulancia, $payload do teste de compatibilidade
    MotivoViagem::porCodigo('alimentacao')->update(['ativo' => false]);
    $this->postJson('/api/viagens', $payload($strada, 'alimentacao'))->assertJsonValidationErrors(['motivo_viagem']);

    $ambos = MotivoViagem::factory()->create(['tipo_veiculo' => 'ambos']);
    $this->postJson('/api/viagens', $payload($ambulancia, $ambos->codigo))->assertCreated();
}

public function test_editar_viagem_mantendo_motivo_inativado_continua_valido(): void
{
    // criar viagem com motivo 'alimentacao' em veículo administrativo (admin logado)
    MotivoViagem::porCodigo('alimentacao')->update(['ativo' => false]);
    $this->putJson("/api/viagens/{$viagem->id}", ['motivo_viagem' => 'alimentacao', 'origem' => 'nova'])->assertOk();
    $this->putJson("/api/viagens/{$viagem->id}", ['motivo_viagem' => 'tfd'])->assertJsonValidationErrors(['motivo_viagem']);
}
```

Solicitação:
```php
public function test_motivo_fora_de_solicitacao_recusado_e_motivo_novo_aceito(): void
{
    // usuário solicitante como nos testes existentes
    $this->postJson('/api/solicitacoes', ['motivo' => 'alimentacao'])->assertJsonValidationErrors(['motivo']);
    $novo = MotivoViagem::factory()->create(['disponivel_solicitacao' => true]);
    $this->postJson('/api/solicitacoes', ['motivo' => $novo->codigo, 'observacoes' => 'levar documentos'])->assertCreated();
}
```

- [ ] **Step 2:** Rodar → FAIL.

- [ ] **Step 3: Rule**

```php
<?php

namespace App\Rules;

use App\Models\MotivoViagem;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MotivoViagemValido implements ValidationRule
{
    public function __construct(private bool $solicitacao = false, private ?string $aceitarInativo = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $motivo = MotivoViagem::porCodigo(is_string($value) ? $value : null);
        $mantido = $this->aceitarInativo !== null && $value === $this->aceitarInativo;

        if (! $motivo || (! $motivo->ativo && ! $mantido) || ($this->solicitacao && ! $motivo->disponivel_solicitacao)) {
            $fail('Motivo de viagem inválido ou inativo.');
        }
    }
}
```

- [ ] **Step 4:** Nos requests:
  - Store viagem: `'motivo_viagem' => ['required', 'string', new MotivoViagemValido]`.
  - Update viagem: `'motivo_viagem' => ['sometimes', 'string', new MotivoViagemValido(aceitarInativo: ($this->route('viagem') ?? $this->route('viagen'))?->motivo_viagem)]`.
  - Solicitação: `'motivo' => ['required', 'string', new MotivoViagemValido(solicitacao: true)]`.
  - Nos dois `after()` de viagem, trocar `! $veiculo->permiteMotivoViagem(...)` por `! MotivoViagem::porCodigo($this->input('motivo_viagem'))?->permiteVeiculo($veiculo)` (o motivo já passou na regra, então não é null).
  - Em `Veiculo.php`, apagar as constantes e `permiteMotivoViagem`. `grep -rn "permiteMotivoViagem\|MOTIVOS_SOMENTE" app tests` deve sair vazio.

- [ ] **Step 5:** `php artisan test` → PASS.
- [ ] **Step 6: Commit** — `feat(motivos): validação de viagens e solicitações pela tabela de motivos`

---

### Task 4: Rótulos no backend (resources, PDF, relatórios, notificações)

**Files:**
- Modify: `app/Http/Resources/ViagemResource.php`, `app/Http/Resources/SolicitacaoResource.php` (adicionar `motivo_nome`)
- Modify: `resources/views/relatorios/pdf/viagens.blade.php` (remover `$motivos`; usar `\App\Models\MotivoViagem::rotulo(...)` onde hoje usa `$motivos[...]`)
- Modify: `app/Http/Controllers/Api/RelatorioController.php::viagensPorMotivo` (rótulo via `MotivoViagem::rotulo`)
- Modify: `app/Notifications/NovaSolicitacaoTransporte.php`, `NovaSolicitacaoDisponivel.php`, `NovaViagemDesignada.php` (linha de e-mail e payload: `motivo_nome`)
- Test: `tests/Feature/MotivoViagem/MotivoViagemRotulosTest.php`

- [ ] **Step 1: Testes falhando**

```php
public function test_viagem_e_solicitacao_expoem_motivo_nome(): void
{
    // criar viagem com 'servicos_administrativos' e admin logado
    $this->getJson("/api/viagens/{$viagem->id}")->assertJsonPath('data.motivo_nome', 'Serviços Administrativos Diversos');
}

public function test_email_de_nova_solicitacao_mostra_nome_do_motivo(): void
{
    $s = Solicitacao::factory()->create(['motivo' => 'buscar_medico']); // ou create manual como nos testes de solicitação
    $mail = (new NovaSolicitacaoTransporte($s))->toMail(Usuario::factory()->create());
    $this->assertContains('Motivo: Buscar Médico em Outra Cidade', $mail->introLines);
}
```
(Ajustar o construtor conforme a assinatura atual da notificação.)

- [ ] **Step 2:** Rodar → FAIL.
- [ ] **Step 3:** Implementar: nos resources `'motivo_nome' => \App\Models\MotivoViagem::rotulo($this->motivo_viagem)` / `($this->motivo)`; nas notificações trocar `{$this->solicitacao->motivo}` por `MotivoViagem::rotulo($this->solicitacao->motivo)` e incluir `'motivo_nome'` no `toArray`; no RelatorioController substituir o `'motivo' => $motivo` por rótulo (manter a chave `motivo` com o nome, como o frontend já espera texto — conferir `RelatorioViagens.jsx`/Dashboard que consome `viagens_por_motivo`); no Blade substituir o mapa.
- [ ] **Step 4:** `php artisan test` → PASS. Gerar o PDF de viagens localmente (`GET /api/relatorios/viagens/pdf`) e conferir que abre.
- [ ] **Step 5: Commit** — `feat(motivos): rótulos de motivo vindos do cadastro`

---

### Task 5: Roteamento de solicitações com motivo novo

**Files:**
- Modify: `app/Services/RoteamentoSolicitacaoService.php`
- Test: teste existente de roteamento (`grep -rln "RoteamentoSolicitacaoService\|motoristasElegiveis" tests`)

**Interfaces:** `regra()` passa a retornar também `['tipo_veiculo' => string]` para motivos não-sistema; `veiculoAtende` trata esse formato.

- [ ] **Step 1: Testes falhando**

```php
public function test_motivo_novo_administrativo_avisa_so_motorista_em_veiculo_administrativo(): void
{
    $motivo = MotivoViagem::factory()->create(['tipo_veiculo' => 'administrativo', 'disponivel_solicitacao' => true]);
    // motorista A com check-in ativo em STRADA; motorista B com check-in em AMBULANCIA UPA (setup como nos testes existentes)
    $ids = app(RoteamentoSolicitacaoService::class)->motoristasElegiveis($solicitacao)->pluck('id');
    $this->assertEquals([$a->id], $ids->all());
}

public function test_motivo_novo_ambos_avisa_os_dois(): void { /* idem com 'ambos' → [$a, $b] */ }

public function test_tfd_continua_sem_motoristas(): void { /* motivo 'tfd' → coleção vazia */ }
```

- [ ] **Step 2:** Rodar → FAIL.
- [ ] **Step 3:** Em `regra()`:

```php
$config = config("solicitacao.roteamento_motoristas.{$solicitacao->motivo}");
if (! $config) {
    $motivo = MotivoViagem::porCodigo($solicitacao->motivo);
    return ($motivo && ! $motivo->sistema)
        ? ['motivo' => $motivo, 'modelos' => [], 'unidade_id' => null]
        : null;
}
```

Em `veiculoAtende()`, logo após o teste de manutenção:

```php
if (isset($regra['motivo'])) {
    return $regra['motivo']->permiteVeiculo($veiculo);
}
```

Atualizar o docblock do `@return` de `regra()` e o comentário de `config/solicitacao.php` ("Motivos ausentes deste mapa: de sistema (ex.: tfd) notificam apenas admin/gestor; cadastrados pela tela vão aos motoristas em veículo compatível").
- [ ] **Step 4:** `php artisan test` → PASS.
- [ ] **Step 5: Commit** — `feat(motivos): roteamento de solicitações com motivos cadastrados`

---

### Task 6: Frontend — fonte única de motivos (painel e solicitações)

**Files:**
- Create: `resources/js/api/motivosViagem.js`
- Create: `resources/js/hooks/useMotivosViagem.js` (conferir se a pasta `hooks` existe; senão criar)
- Modify: `resources/js/utils/solicitacao.js` (remover `MOTIVOS_SOLICITACAO`, `opcoesMotivo`; manter `rotuloMotivo` só se ainda usado, recebendo a lista)
- Modify: `resources/js/pages/viagens/ViagemForm.jsx` e `ViagemForm.test.js`
- Modify: `resources/js/pages/relatorios/RelatorioViagens.jsx`, `resources/js/pages/solicitacoes/SolicitacoesList.jsx` (usar `motivo_nome` do item)
- Modify: `resources/solicitacao-js/pages/NovaSolicitacao.jsx`, `MinhasSolicitacoes.jsx`
- Antes de começar: `grep -rn "MOTIVOS_SOLICITACAO\|opcoesMotivo\|rotuloMotivo" resources/` para pegar todos os consumidores (incluindo testes).

**Interfaces — Produces:**
- `api/motivosViagem.js`: `listar(params)`, `criar(data)`, `atualizar(id, data)`, `excluir(id)` (PUT para atualizar).
- `useMotivosViagem(params = {})` → `{ motivos: Array<{codigo, nome, tipo_veiculo, ...}>, isLoading }`, queryKey `['motivos-viagem', params]`, `staleTime: 5 * 60_000`.
- `motivosDoVeiculo(motivos, veiculo)` (exportada de `ViagemForm.jsx`).

- [ ] **Step 1: Teste falhando** — reescrever `ViagemForm.test.js`:

```js
import { describe, it, expect } from 'vitest'
import { motivosDoVeiculo } from './ViagemForm'

const motivos = [
  { codigo: 'tfd', nome: 'TFD', tipo_veiculo: 'ambulancia' },
  { codigo: 'alimentacao', nome: 'Alimentação', tipo_veiculo: 'administrativo' },
  { codigo: 'lavar', nome: 'Lavar', tipo_veiculo: 'ambos' },
]
const codigos = (v) => motivosDoVeiculo(motivos, v).map(m => m.codigo)

describe('motivosDoVeiculo', () => {
  it('ambulância vê ambulância + ambos', () => {
    expect(codigos({ modelo: 'AMBULÂNCIA SPRINTER' })).toEqual(['tfd', 'lavar'])
  })
  it('administrativo vê administrativo + ambos', () => {
    expect(codigos({ modelo: 'STRADA' })).toEqual(['alimentacao', 'lavar'])
  })
  it('sem veículo mostra todos', () => {
    expect(codigos(null)).toHaveLength(3)
  })
})
```

- [ ] **Step 2:** `npx vitest run resources/js/pages/viagens` → FAIL.
- [ ] **Step 3: Implementar**

`api/motivosViagem.js`:
```js
import api from './axios'

export const listar = (params) => api.get('/motivos-viagem', { params })
export const criar = (data) => api.post('/motivos-viagem', data)
export const atualizar = (id, data) => api.put(`/motivos-viagem/${id}`, data)
export const excluir = (id) => api.delete(`/motivos-viagem/${id}`)
```

`hooks/useMotivosViagem.js`:
```js
import { useQuery } from '@tanstack/react-query'
import * as motivosApi from '../api/motivosViagem'

export function useMotivosViagem(params = {}) {
  const { data, isLoading } = useQuery({
    queryKey: ['motivos-viagem', params],
    queryFn: () => motivosApi.listar(params).then(r => r.data.data),
    staleTime: 5 * 60_000,
  })
  return { motivos: data ?? [], isLoading }
}
```

`ViagemForm.jsx`: remover `MOTIVOS`, `SOMENTE_*` e import de `opcoesMotivo`; nova função:
```js
// Sem veículo selecionado mostra todos os motivos ativos.
export function motivosDoVeiculo(motivos, veiculo) {
  if (!veiculo?.modelo) return motivos
  const tipo = ehAmbulancia(veiculo) ? 'ambulancia' : 'administrativo'
  return motivos.filter(m => m.tipo_veiculo === 'ambos' || m.tipo_veiculo === tipo)
}
```
No componente: `const { motivos } = useMotivosViagem()`; no `<select>` mapear `{ value: m.codigo, label: m.nome }`. Se o motivo selecionado deixar de estar na lista filtrada ao trocar o veículo, manter o comportamento atual do formulário (conferir como já é tratado).

Telas de exibição: trocar `MOTIVOS_SOLICITACAO[x] ?? x` por `item.motivo_nome ?? item.motivo` (`motivo_viagem` no relatório). Para `aceitarTarget`/`recusarTarget` idem.

`NovaSolicitacao.jsx`: `const { motivos } = useMotivosViagem({ solicitacao: 1 })` (importar de `../../js/hooks/useMotivosViagem`; o app de solicitação precisa de `QueryClientProvider` — conferir no entrypoint de `resources/solicitacao-js`; se não houver React Query lá, usar `useEffect` + `api.get('/motivos-viagem', { params: { solicitacao: 1 } })` com o axios que esse app já usa). Os `precisa*` continuam por código; nenhum campo extra para motivos novos.

- [ ] **Step 4:** `npm test` → PASS; `npm run build` sem erro.
- [ ] **Step 5: Commit** — `feat(motivos): frontend lê motivos da API`

---

### Task 7: Página Configurações → Motivos de Viagem

**Files:**
- Create: `resources/js/pages/configuracoes/MotivosViagem.jsx`
- Create: `resources/js/pages/configuracoes/MotivosViagem.test.jsx`
- Modify: `resources/js/pages/configuracoes/ConfiguracoesHub.jsx` (novo item em `secoes`, ícone `ListChecks` do lucide-react)
- Modify: `resources/js/FleetApp.jsx` (rota `/configuracoes/motivos-viagem` com `AdminRoute`, igual à de LDAP)
- Modify: `resources/js/components/layout/Layout.jsx` (título `'/configuracoes/motivos-viagem': 'Motivos de Viagem'`, se o mapa tiver as subrotas)

**Interfaces — Consumes:** `api/motivosViagem.js`, `Modal`, `Alert`, `LoadingSpinner` (mesmos de `LocalidadesList.jsx`).

- [ ] **Step 1: Teste falhando** (mockar `../../api/motivosViagem` com `vi.mock`, envolver em `QueryClientProvider` como nos testes existentes — copiar o helper de render de `Sidebar.test.jsx` ou outro teste de página):

```jsx
const dados = [
  { id: '1', codigo: 'tfd', nome: 'TFD', tipo_veiculo: 'ambulancia', disponivel_solicitacao: true, ativo: true, sistema: true, em_uso: true },
  { id: '2', codigo: 'lavar', nome: 'Lavar Veículo', tipo_veiculo: 'ambos', disponivel_solicitacao: false, ativo: true, sistema: false, em_uso: false },
  { id: '3', codigo: 'velho', nome: 'Antigo', tipo_veiculo: 'administrativo', disponivel_solicitacao: false, ativo: false, sistema: false, em_uso: true },
]

it('lista ativos, mostra selo Sistema e Excluir só no não usado', async () => {
  render(<MotivosViagem />)
  expect(await screen.findByText('TFD')).toBeInTheDocument()
  expect(screen.getByText('Sistema')).toBeInTheDocument()
  expect(screen.queryByText('Antigo')).not.toBeInTheDocument()
  expect(screen.getAllByRole('button', { name: /excluir/i })).toHaveLength(1)
})

it('mostrar inativos exibe o inativo', async () => {
  render(<MotivosViagem />)
  await userEvent.click(await screen.findByLabelText(/mostrar inativos/i))
  expect(screen.getByText('Antigo')).toBeInTheDocument()
})
```

- [ ] **Step 2:** Rodar → FAIL.
- [ ] **Step 3: Implementar** `MotivosViagem.jsx` seguindo a estrutura visual de `LocalidadesList.jsx` (cabeçalho com botão "Novo motivo", tabela, modal):
  - `useQuery(['motivos-viagem', { todos: 1 }], () => motivosApi.listar({ todos: 1 }).then(r => r.data.data))`.
  - Checkbox "Mostrar inativos" (estado local) filtra `ativo`.
  - Colunas: Nome (+ selo "Sistema" cinza), Tipo de veículo (`{ administrativo: 'Administrativo', ambulancia: 'Ambulância', ambos: 'Ambos' }`), Solicitações (Sim/Não), Status (Ativo/Inativo), ações: Editar, Inativar/Reativar (`atualizar(id, { ativo })`), Excluir (só `!sistema && !em_uso`).
  - Modal com form: Nome (obrigatório), Tipo de veículo (select; `disabled` quando `sistema`), checkbox "Disponível em solicitações", checkbox "Ativo". Erros da API (`e.response.data.errors` / `message`) em `<Alert>`.
  - Toda mutação invalida `['motivos-viagem']` (prefixo — também atualiza os selects dos formulários).
  - Excluir: sem `window.confirm` (bloqueia automação); usar o mesmo padrão de confirmação em modal usado no projeto (ver `SolicitacoesList.jsx` recusar) ou um segundo clique "Confirmar exclusão".
  - Hub: `{ to: '/configuracoes/motivos-viagem', titulo: 'Motivos de Viagem', descricao: 'Cadastre os motivos de viagem, defina para quais veículos valem e quais aparecem nas solicitações.', icon: ListChecks }`.
- [ ] **Step 4:** `npm test` → PASS; `npm run build`.
- [ ] **Step 5: Commit** — `feat(motivos): tela de cadastro em Configurações`

---

### Task 8: Validação no navegador e deploy

- [ ] **Step 1:** `php artisan migrate` local; `php artisan test` e `npm test` completos → PASS.
- [ ] **Step 2 (navegador, localhost):** como admin: criar "Lavagem de Veículo" (ambos, disponível em solicitações); conferir no formulário de nova viagem que aparece para Strada e ambulância; inativar e conferir que some; tentar excluir TFD (botão ausente); conferir app `/solicitar` listando o motivo novo. Apagar o motivo de teste ao final.
- [ ] **Step 3:** Commit restante e `git push origin main`.
- [ ] **Step 4 (produção):** backup MySQL conforme memória `producao_acesso` (`mysqldump --single-transaction` → `~/backups/healthdrive-2026-10-08-pre-deploy.sql.gz`, validar `gzip -t`); conferir `git status` no servidor (stash das pendências de backup se ainda existirem, `stash pop` após); `bash scripts/deploy.sh`; health check; `php artisan tinker --execute="echo App\Models\MotivoViagem::count();"` → 8.
