# Aceite/recusa de viagem designada pelo gestor (motorista) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Quando o gestor designa motorista/veículo para uma `Solicitacao`, o motorista passa a receber um pop-up "Nova Viagem Designada pelo Gestor" (sem fechamento automático) com ACEITAR/RECUSAR; a `Viagem` só é criada quando o motorista efetivamente informar o KM de saída, e recusas voltam ao gestor com motivo para redesignação.

**Architecture:** O gestor deixa de efetivar a viagem em `SolicitacaoService::aceitar()` — passa a apenas designar (`status = pendente_motorista`) e notificar o motorista via Laravel Notifications (canal `database`, mesmo mecanismo de polling de 20s já usado no pop-up do gestor). Dois novos endpoints (`motorista-aceitar`, `motorista-recusar`) efetivam a decisão do motorista. A fila de múltiplas viagens usa o campo `aguardando_finalizacao_trajeto` já existente, mas `processarPendentePara()` deixa de criar a `Viagem` sozinha — apenas notifica o motorista para informar o KM quando a vez chegar.

**Tech Stack:** Laravel 11 (PHP 8), SQLite, PHPUnit, React 18 + Vite, TanStack Query, Tailwind.

## Global Constraints

- UUID como chave primária em todos os models (`HasUuids`).
- SQLite não suporta `ALTER TABLE ... MODIFY COLUMN` para `enum` — alterar o enum de `status` exige recriar a tabela (`Schema::create` tabela nova + `INSERT ... SELECT` + `drop`/`rename`), seguindo o padrão de `database/migrations/2026_08_05_000001_add_pendente_fields_to_solicitacoes_table.php`.
- Motivo de recusa nunca deve ser exposto ao solicitante original — só a `SolicitacaoResource` usada nas telas de gestor/admin deve incluí-lo, e nenhuma notificação deve ir ao `usuario_id` da solicitação.
- Sem WebSockets/broadcast: toda notificação em tempo quase-real usa o polling de 20s já existente (`useNotificacoes`, `refetchInterval: 20_000`).
- Sem framework de teste JS no projeto — tarefas de frontend são verificadas com `npm run build` e leitura de código, não há testes automatizados de UI.

---

### Task 1: Migration — novos status e `motivo_recusa`

**Files:**
- Create: `database/migrations/2026_08_07_000001_add_recusa_e_status_pendente_to_solicitacoes_table.php`
- Test: `tests/Feature/Solicitacao/SolicitacaoApiTest.php` (reaproveitado nas próximas tarefas — esta tarefa só valida a migration via `php artisan migrate:fresh --seed=false` local, não precisa de teste PHPUnit dedicado)

**Interfaces:**
- Produces: coluna `solicitacoes.motivo_recusa` (`text`, nullable); `solicitacoes.status` aceita agora `['aberto', 'pendente_motorista', 'em_trajeto', 'aguardando_finalizacao_trajeto', 'recusada', 'finalizado', 'cancelado']`.

- [ ] **Step 1: Escrever a migration**

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
        Schema::create('solicitacoes_new', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id');
            $table->uuid('unidade_id')->nullable();
            $table->string('motivo');
            $table->uuid('origem_unidade_id')->nullable();
            $table->uuid('destino_unidade_id')->nullable();
            $table->unsignedInteger('numero_atendimento')->nullable();
            $table->string('cidade', 150)->nullable();
            $table->string('hospital_destino', 150)->nullable();
            $table->string('fornecedor_nome', 150)->nullable();
            $table->enum('status', ['aberto', 'pendente_motorista', 'em_trajeto', 'aguardando_finalizacao_trajeto', 'recusada', 'finalizado', 'cancelado'])->default('aberto');
            $table->uuid('viagem_id')->nullable();
            $table->uuid('motorista_pendente_id')->nullable();
            $table->uuid('veiculo_pendente_id')->nullable();
            $table->text('motivo_recusa')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('usuarios');
            $table->foreign('unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('origem_unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('destino_unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('viagem_id')->references('id')->on('viagens')->nullOnDelete();
            $table->foreign('motorista_pendente_id')->references('id')->on('motoristas')->nullOnDelete();
            $table->foreign('veiculo_pendente_id')->references('id')->on('veiculos')->nullOnDelete();
        });

        DB::statement('INSERT INTO solicitacoes_new (id, usuario_id, unidade_id, motivo, origem_unidade_id, destino_unidade_id, numero_atendimento, cidade, hospital_destino, fornecedor_nome, status, viagem_id, motorista_pendente_id, veiculo_pendente_id, observacoes, created_at, updated_at)
            SELECT id, usuario_id, unidade_id, motivo, origem_unidade_id, destino_unidade_id, numero_atendimento, cidade, hospital_destino, fornecedor_nome, status, viagem_id, motorista_pendente_id, veiculo_pendente_id, observacoes, created_at, updated_at FROM solicitacoes');

        Schema::drop('solicitacoes');
        Schema::rename('solicitacoes_new', 'solicitacoes');
    }

    public function down(): void
    {
        Schema::create('solicitacoes_old', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id');
            $table->uuid('unidade_id')->nullable();
            $table->string('motivo');
            $table->uuid('origem_unidade_id')->nullable();
            $table->uuid('destino_unidade_id')->nullable();
            $table->unsignedInteger('numero_atendimento')->nullable();
            $table->string('cidade', 150)->nullable();
            $table->string('hospital_destino', 150)->nullable();
            $table->string('fornecedor_nome', 150)->nullable();
            $table->enum('status', ['aberto', 'em_trajeto', 'aguardando_finalizacao_trajeto', 'finalizado', 'cancelado'])->default('aberto');
            $table->uuid('viagem_id')->nullable();
            $table->uuid('motorista_pendente_id')->nullable();
            $table->uuid('veiculo_pendente_id')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('usuarios');
            $table->foreign('unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('origem_unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('destino_unidade_id')->references('id')->on('unidades')->nullOnDelete();
            $table->foreign('viagem_id')->references('id')->on('viagens')->nullOnDelete();
            $table->foreign('motorista_pendente_id')->references('id')->on('motoristas')->nullOnDelete();
            $table->foreign('veiculo_pendente_id')->references('id')->on('veiculos')->nullOnDelete();
        });

        DB::statement("INSERT INTO solicitacoes_old (id, usuario_id, unidade_id, motivo, origem_unidade_id, destino_unidade_id, numero_atendimento, cidade, hospital_destino, fornecedor_nome, status, viagem_id, motorista_pendente_id, veiculo_pendente_id, observacoes, created_at, updated_at)
            SELECT id, usuario_id, unidade_id, motivo, origem_unidade_id, destino_unidade_id, numero_atendimento, cidade, hospital_destino, fornecedor_nome, CASE WHEN status IN ('pendente_motorista', 'recusada') THEN 'aberto' ELSE status END, viagem_id, motorista_pendente_id, veiculo_pendente_id, observacoes, created_at, updated_at FROM solicitacoes");

        Schema::drop('solicitacoes');
        Schema::rename('solicitacoes_old', 'solicitacoes');
    }
};
```

- [ ] **Step 2: Rodar a migration localmente**

Run: `php artisan migrate`
Expected: migration `2026_08_07_000001_add_recusa_e_status_pendente_to_solicitacoes_table` aplicada sem erro; `php artisan tinker --execute="dd(Schema::getColumnListing('solicitacoes'))"` mostra `motivo_recusa`.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_08_07_000001_add_recusa_e_status_pendente_to_solicitacoes_table.php
git commit -m "feat: adiciona status pendente_motorista/recusada e motivo_recusa em solicitacoes"
```

---

### Task 2: Notifications `NovaViagemDesignada` e `SolicitacaoRecusadaPeloMotorista`

**Files:**
- Create: `app/Notifications/NovaViagemDesignada.php`
- Create: `app/Notifications/SolicitacaoRecusadaPeloMotorista.php`
- Test: `tests/Feature/Solicitacao/SolicitacaoApiTest.php` (asserções de notificação entram na Task 3, junto com o service que as dispara)

**Interfaces:**
- Produces: `NovaViagemDesignada` — construtor `(Solicitacao $solicitacao, bool $fila = false)`; `toArray()` retorna `['solicitacao_id', 'motivo', 'detalhe', 'fila']`.
- Produces: `SolicitacaoRecusadaPeloMotorista` — construtor `(Solicitacao $solicitacao, string $motoristaNome, string $motivo)`; `toArray()` retorna `['solicitacao_id', 'motorista_nome', 'motivo_recusa', 'detalhe']`.

- [ ] **Step 1: Criar `NovaViagemDesignada`**

```php
<?php

namespace App\Notifications;

use App\Models\Solicitacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NovaViagemDesignada extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Solicitacao $solicitacao, protected bool $fila = false) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'solicitacao_id' => $this->solicitacao->id,
            'motivo' => $this->solicitacao->motivo,
            'detalhe' => $this->detalhe(),
            'fila' => $this->fila,
        ];
    }

    private function detalhe(): string
    {
        $this->solicitacao->loadMissing(['origemUnidade', 'destinoUnidade']);

        if ($this->solicitacao->origemUnidade || $this->solicitacao->destinoUnidade) {
            return ($this->solicitacao->origemUnidade?->nome ?? '—').' → '.($this->solicitacao->destinoUnidade?->nome ?? '—');
        }

        return $this->solicitacao->cidade ?? $this->solicitacao->hospital_destino ?? 'Sem detalhe';
    }
}
```

- [ ] **Step 2: Criar `SolicitacaoRecusadaPeloMotorista`**

```php
<?php

namespace App\Notifications;

use App\Models\Solicitacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SolicitacaoRecusadaPeloMotorista extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Solicitacao $solicitacao,
        protected string $motoristaNome,
        protected string $motivo
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'solicitacao_id' => $this->solicitacao->id,
            'motorista_nome' => $this->motoristaNome,
            'motivo_recusa' => $this->motivo,
        ];
    }
}
```

- [ ] **Step 3: Verificar que o projeto compila (sem testes ainda, classes ainda não usadas)**

Run: `php artisan tinker --execute="new App\Notifications\NovaViagemDesignada(new App\Models\Solicitacao()); new App\Notifications\SolicitacaoRecusadaPeloMotorista(new App\Models\Solicitacao(), 'x', 'y'); echo 'ok';"`
Expected: imprime `ok` sem erro fatal.

- [ ] **Step 4: Commit**

```bash
git add app/Notifications/NovaViagemDesignada.php app/Notifications/SolicitacaoRecusadaPeloMotorista.php
git commit -m "feat: notificações de designação e recusa de viagem para o motorista"
```

---

### Task 3: `SolicitacaoService` — designar em vez de efetivar, `motoristaAceitar`, `motoristaRecusar`

**Files:**
- Modify: `app/Services/SolicitacaoService.php`
- Modify: `tests/Feature/Solicitacao/SolicitacaoApiTest.php:58-73` (`test_gestor_aceita_solicitacao_e_cria_viagem_vinculada` precisa virar "designa" em vez de "cria viagem")
- Modify: `tests/Feature/Solicitacao/SolicitacaoApiTest.php:75-153` (os três testes de troca de veículo/checkin dependem de `efetivarAceite` — passam a chamar o novo `motoristaAceitar` depois do `aceitar` do gestor)

**Interfaces:**
- Consumes: `Motorista::usuario(): HasOne<Usuario>` (`app/Models/Motorista.php:21`), `Usuario::where('perfil', ...)` (padrão já usado em `store()`).
- Produces: `aceitar(Solicitacao $solicitacao, string $motoristaId, string $veiculoId): Solicitacao` (agora só designa, status `pendente_motorista`, não cria `Viagem`).
- Produces: `motoristaAceitar(Solicitacao $solicitacao, string $motoristaId, ?int $kmSaida = null): Solicitacao`.
- Produces: `motoristaRecusar(Solicitacao $solicitacao, string $motoristaId, string $motivo): Solicitacao`.
- Produces: `processarPendentePara(string $motoristaId, ?int $kmRetornoTrajetoAnterior = null): ?Solicitacao` (assinatura mantida por compatibilidade com `ViagemService::chegada`, mas agora só notifica, não cria `Viagem`; `$kmRetornoTrajetoAnterior` deixa de ser usado — mantido no parâmetro para não quebrar a chamada existente, mas o valor não é mais repassado como `km_saida`).

- [ ] **Step 1: Reescrever os testes existentes que dependiam da criação imediata da `Viagem` pelo gestor**

Substituir em `tests/Feature/Solicitacao/SolicitacaoApiTest.php`:

```php
    public function test_gestor_designa_motorista_e_solicitacao_fica_pendente_motorista(): void
    {
        $this->loginGestor();
        $solicitacao = Solicitacao::factory()->create(['motivo' => 'tfd', 'status' => 'aberto']);
        $motorista = Motorista::factory()->create();
        $veiculo = Veiculo::factory()->create();

        $response = $this->patchJson("/api/solicitacoes/{$solicitacao->id}/aceitar", [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'pendente_motorista');
        $this->assertDatabaseHas('solicitacoes', [
            'id' => $solicitacao->id,
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motorista->id,
            'veiculo_pendente_id' => $veiculo->id,
        ]);
        $this->assertDatabaseMissing('viagens', ['motorista_id' => $motorista->id, 'veiculo_id' => $veiculo->id]);
    }

    public function test_motorista_aceita_sem_viagem_ativa_informando_km_saida_cria_viagem(): void
    {
        $motorista = Motorista::factory()->create();
        $usuarioMotorista = Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $motorista->id]);
        $veiculo = Veiculo::factory()->create();
        $solicitacao = Solicitacao::factory()->create([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motorista->id,
            'veiculo_pendente_id' => $veiculo->id,
        ]);

        $token = $usuarioMotorista->createToken('test')->plainTextToken;
        $this->withToken($token);

        $response = $this->patchJson("/api/solicitacoes/{$solicitacao->id}/motorista-aceitar", ['km_saida' => 1500]);

        $response->assertOk()->assertJsonPath('data.status', 'em_trajeto');
        $this->assertDatabaseHas('viagens', [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
            'km_saida' => 1500,
            'status' => 'em_andamento',
        ]);
    }

    public function test_motorista_aceita_com_viagem_ativa_fica_aguardando_sem_km(): void
    {
        $motorista = Motorista::factory()->create();
        $usuarioMotorista = Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $motorista->id]);
        $veiculoAtual = Veiculo::factory()->create(['status' => 'em_uso']);
        $veiculoNovo = Veiculo::factory()->create(['status' => 'disponivel']);

        Viagem::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoAtual->id,
            'status' => 'em_andamento',
        ]);

        $solicitacao = Solicitacao::factory()->create([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motorista->id,
            'veiculo_pendente_id' => $veiculoNovo->id,
        ]);

        $token = $usuarioMotorista->createToken('test')->plainTextToken;
        $this->withToken($token);

        $response = $this->patchJson("/api/solicitacoes/{$solicitacao->id}/motorista-aceitar", []);

        $response->assertOk()->assertJsonPath('data.status', 'aguardando_finalizacao_trajeto');
        $this->assertDatabaseMissing('viagens', ['motorista_id' => $motorista->id, 'veiculo_id' => $veiculoNovo->id]);
    }

    public function test_motorista_aceita_sem_viagem_ativa_e_sem_km_falha_422(): void
    {
        $motorista = Motorista::factory()->create();
        $usuarioMotorista = Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $motorista->id]);
        $veiculo = Veiculo::factory()->create();
        $solicitacao = Solicitacao::factory()->create([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motorista->id,
            'veiculo_pendente_id' => $veiculo->id,
        ]);

        $token = $usuarioMotorista->createToken('test')->plainTextToken;
        $this->withToken($token);

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/motorista-aceitar", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['km_saida']);
    }

    public function test_motorista_recusa_com_motivo_gestor_pode_redesignar(): void
    {
        $motorista = Motorista::factory()->create();
        $usuarioMotorista = Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $motorista->id]);
        $veiculo = Veiculo::factory()->create();
        $solicitacao = Solicitacao::factory()->create([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motorista->id,
            'veiculo_pendente_id' => $veiculo->id,
        ]);

        $token = $usuarioMotorista->createToken('test')->plainTextToken;
        $this->withToken($token);

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/motorista-recusar", ['motivo' => 'Veículo com problema mecânico'])
            ->assertOk()
            ->assertJsonPath('data.status', 'recusada');

        $this->assertDatabaseHas('solicitacoes', [
            'id' => $solicitacao->id,
            'status' => 'recusada',
            'motivo_recusa' => 'Veículo com problema mecânico',
            'motorista_pendente_id' => null,
            'veiculo_pendente_id' => null,
        ]);

        $this->loginGestor();
        $outroMotorista = Motorista::factory()->create();
        $outroVeiculo = Veiculo::factory()->create();

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/aceitar", [
            'motorista_id' => $outroMotorista->id,
            'veiculo_id' => $outroVeiculo->id,
        ])->assertOk()->assertJsonPath('data.status', 'pendente_motorista');
    }

    public function test_motorista_nao_pode_aceitar_solicitacao_de_outro_motorista(): void
    {
        $motoristaDesignado = Motorista::factory()->create();
        $outroMotorista = Motorista::factory()->create();
        $usuarioOutroMotorista = Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $outroMotorista->id]);
        $veiculo = Veiculo::factory()->create();
        $solicitacao = Solicitacao::factory()->create([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motoristaDesignado->id,
            'veiculo_pendente_id' => $veiculo->id,
        ]);

        $token = $usuarioOutroMotorista->createToken('test')->plainTextToken;
        $this->withToken($token);

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/motorista-aceitar", ['km_saida' => 100])
            ->assertForbidden();
    }
```

Remover os testes antigos `test_gestor_aceita_solicitacao_e_cria_viagem_vinculada`, `test_aceitar_com_motorista_em_outro_veiculo_troca_checkin_automaticamente`, `test_aceitar_com_troca_de_veiculo_apos_viagem_anterior_ja_concluida_preenche_km_retorno`, `test_aceitar_motorista_com_viagem_em_andamento_fica_aguardando_finalizacao` e `test_finalizar_trajeto_efetiva_solicitacao_pendente_com_troca_de_veiculo` — a lógica de troca de checkin/veículo (`efetivarAceite`/`trocarVeiculoDoCheckin`) permanece, mas agora só é exercitada a partir do fluxo do motorista (`motorista-aceitar`), coberta pela Task 6.

- [ ] **Step 2: Rodar os testes para confirmar que falham (métodos ainda não existem)**

Run: `php artisan test --filter=SolicitacaoApiTest`
Expected: FAIL em `test_gestor_designa_motorista_e_solicitacao_fica_pendente_motorista` (ainda assume status antigo) e erro de rota inexistente (`404`) nos testes de `motorista-aceitar`/`motorista-recusar`.

- [ ] **Step 3: Reescrever `SolicitacaoService`**

```php
<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\Motorista;
use App\Models\Solicitacao;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Notifications\NovaSolicitacaoTransporte;
use App\Notifications\NovaViagemDesignada;
use App\Notifications\SolicitacaoRecusadaPeloMotorista;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SolicitacaoService
{
    public function __construct(private CheckinService $checkinService) {}

    public function store(array $data, Usuario $usuario): Solicitacao
    {
        $data['usuario_id'] = $usuario->id;
        $data['unidade_id'] = $usuario->unidade_id;
        $data['status'] = 'aberto';

        $solicitacao = Solicitacao::create($data);

        $destinatarios = Usuario::where('perfil', 'admin')
            ->orWhere(function ($q) use ($solicitacao) {
                $q->where('perfil', 'gestor')
                    ->where(function ($q) use ($solicitacao) {
                        $q->where('unidade_id', $solicitacao->unidade_id)
                            ->orWhereHas('unidade', fn ($q) => $q->where('tipo', 'matriz'));
                    });
            })
            ->get();

        Notification::send($destinatarios, new NovaSolicitacaoTransporte($solicitacao));

        return $solicitacao;
    }

    /**
     * Gestor designa motorista/veículo. Não cria mais a Viagem aqui — apenas
     * marca a solicitação como pendente da confirmação do motorista.
     */
    public function aceitar(Solicitacao $solicitacao, string $motoristaId, string $veiculoId): Solicitacao
    {
        $solicitacao->update([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motoristaId,
            'veiculo_pendente_id' => $veiculoId,
            'motivo_recusa' => null,
        ]);

        $motorista = Motorista::with('usuario')->findOrFail($motoristaId);
        if ($motorista->usuario) {
            Notification::send($motorista->usuario, new NovaViagemDesignada($solicitacao->fresh()));
        }

        return $solicitacao->fresh();
    }

    /**
     * Motorista aceita a designação. Sem viagem ativa, exige km_saida e cria a Viagem.
     * Com viagem ativa, entra/permanece na fila (aguardando_finalizacao_trajeto).
     */
    public function motoristaAceitar(Solicitacao $solicitacao, string $motoristaId, ?int $kmSaida = null): Solicitacao
    {
        return DB::transaction(function () use ($solicitacao, $motoristaId, $kmSaida) {
            $emViagem = Viagem::where('motorista_id', $motoristaId)
                ->where('status', 'em_andamento')
                ->exists();

            if ($emViagem && $kmSaida === null) {
                $solicitacao->update(['status' => 'aguardando_finalizacao_trajeto']);

                return $solicitacao->fresh();
            }

            if ($kmSaida === null) {
                throw ValidationException::withMessages([
                    'km_saida' => 'Informe o KM de saída para iniciar a viagem.',
                ]);
            }

            return $this->efetivarAceite($solicitacao, $motoristaId, $solicitacao->veiculo_pendente_id, $kmSaida);
        });
    }

    public function motoristaRecusar(Solicitacao $solicitacao, string $motoristaId, string $motivo): Solicitacao
    {
        $motorista = Motorista::findOrFail($motoristaId);

        $solicitacao->update([
            'status' => 'recusada',
            'motivo_recusa' => $motivo,
            'motorista_pendente_id' => null,
            'veiculo_pendente_id' => null,
        ]);

        $destinatarios = Usuario::where('perfil', 'admin')
            ->orWhere(function ($q) use ($solicitacao) {
                $q->where('perfil', 'gestor')
                    ->where(function ($q) use ($solicitacao) {
                        $q->where('unidade_id', $solicitacao->unidade_id)
                            ->orWhereHas('unidade', fn ($q) => $q->where('tipo', 'matriz'));
                    });
            })
            ->get();

        Notification::send($destinatarios, new SolicitacaoRecusadaPeloMotorista($solicitacao->fresh(), $motorista->nome, $motivo));

        return $solicitacao->fresh();
    }

    /**
     * Chamado após a conclusão de uma viagem: avisa o motorista para informar
     * o KM de saída da próxima da fila (FIFO). Não cria a Viagem sozinha.
     */
    public function processarPendentePara(string $motoristaId, ?int $kmRetornoTrajetoAnterior = null): ?Solicitacao
    {
        $solicitacao = Solicitacao::where('status', 'aguardando_finalizacao_trajeto')
            ->where('motorista_pendente_id', $motoristaId)
            ->oldest()
            ->first();

        if (! $solicitacao) {
            return null;
        }

        $motorista = Motorista::with('usuario')->find($motoristaId);
        if ($motorista?->usuario) {
            Notification::send($motorista->usuario, new NovaViagemDesignada($solicitacao, fila: true));
        }

        return $solicitacao;
    }

    public function cancelar(Solicitacao $solicitacao): Solicitacao
    {
        $solicitacao->update(['status' => 'cancelado']);

        return $solicitacao->fresh();
    }

    private function efetivarAceite(Solicitacao $solicitacao, string $motoristaId, string $veiculoId, int $kmSaida): Solicitacao
    {
        $motorista = Motorista::with('checkinAtivo')->findOrFail($motoristaId);
        $checkin = $motorista->checkinAtivo;

        if ($checkin && $checkin->veiculo_id !== $veiculoId) {
            $checkin = $this->trocarVeiculoDoCheckin($checkin, $veiculoId, $kmSaida);
        }

        $viagem = Viagem::create([
            'veiculo_id' => $veiculoId,
            'motorista_id' => $motoristaId,
            'checkin_id' => $checkin?->id,
            'origem' => $solicitacao->origemUnidade?->nome ?? $solicitacao->cidade ?? $solicitacao->hospital_destino ?? '',
            'destino' => $solicitacao->destinoUnidade?->nome ?? $solicitacao->hospital_destino ?? '',
            'motivo_viagem' => $solicitacao->motivo,
            'numero_atendimento' => $solicitacao->numero_atendimento,
            'km_saida' => $kmSaida,
            'saida_at' => now(),
            'status' => 'em_andamento',
        ]);

        $solicitacao->update([
            'viagem_id' => $viagem->id,
            'status' => 'em_trajeto',
            'motorista_pendente_id' => null,
            'veiculo_pendente_id' => null,
        ]);

        return $solicitacao->fresh();
    }

    private function trocarVeiculoDoCheckin(Checkin $checkin, string $veiculoId, ?int $kmRetorno = null): Checkin
    {
        $this->checkinService->checkout($checkin, $kmRetorno !== null ? ['km_retorno' => $kmRetorno] : []);

        $veiculo = Veiculo::findOrFail($veiculoId);

        return $this->checkinService->store([
            'motorista_id' => $checkin->motorista_id,
            'veiculo_id' => $veiculoId,
            'turno' => $checkin->turno,
            'km_saida' => $veiculo->km_atual,
        ]);
    }
}
```

Nota: `trocarVeiculoDoCheckin` agora recebe o `km_saida` informado pelo motorista como `km_retorno` do checkin antigo (mesma lógica de antes, só que a origem do valor mudou de "KM de chegada da viagem anterior" para "KM de saída informado pelo motorista" — ambos representam o KM atual do veículo/motorista no momento da troca).

- [ ] **Step 4: Rodar os testes de `SolicitacaoApiTest` de novo**

Run: `php artisan test --filter=SolicitacaoApiTest`
Expected: ainda falham os testes que chamam `/motorista-aceitar` e `/motorista-recusar` (rota 404) — resolvido na Task 4. `test_gestor_designa_motorista_e_solicitacao_fica_pendente_motorista` deve passar.

- [ ] **Step 5: Commit**

```bash
git add app/Services/SolicitacaoService.php tests/Feature/Solicitacao/SolicitacaoApiTest.php
git commit -m "feat: gestor designa e motorista efetiva a viagem via SolicitacaoService"
```

---

### Task 4: Endpoints e autorização — `motorista-aceitar` / `motorista-recusar`

**Files:**
- Modify: `app/Http/Controllers/Api/SolicitacaoController.php`
- Modify: `routes/api.php:73-76`
- Test: `tests/Feature/Solicitacao/SolicitacaoApiTest.php` (testes já escritos na Task 3 exercitam estas rotas)

**Interfaces:**
- Consumes: `SolicitacaoService::motoristaAceitar()`, `SolicitacaoService::motoristaRecusar()` (Task 3).
- Consumes: `Usuario::motorista_id` (`app/Models/Usuario.php:29`) para verificar que o usuário autenticado é o motorista designado.
- Produces: rotas `PATCH /api/solicitacoes/{solicitacao}/motorista-aceitar` e `PATCH /api/solicitacoes/{solicitacao}/motorista-recusar`.

- [ ] **Step 1: Adicionar as rotas**

Em `routes/api.php`, logo após a linha 75 (`Route::patch('solicitacoes/{solicitacao}/cancelar', ...)`):

```php
    Route::patch('solicitacoes/{solicitacao}/motorista-aceitar', [SolicitacaoController::class, 'motoristaAceitar'])->name('solicitacoes.motorista-aceitar');
    Route::patch('solicitacoes/{solicitacao}/motorista-recusar', [SolicitacaoController::class, 'motoristaRecusar'])->name('solicitacoes.motorista-recusar');
```

- [ ] **Step 2: Adicionar as actions no controller**

Em `app/Http/Controllers/Api/SolicitacaoController.php`, após o método `aceitar` (linha 68):

```php
    public function motoristaAceitar(Request $r, Solicitacao $solicitacao)
    {
        $user = $r->user();
        if (! $user->motorista_id || $solicitacao->motorista_pendente_id !== $user->motorista_id) {
            return response()->json(['error' => 'Esta viagem não está designada para você.'], 403);
        }

        if (! in_array($solicitacao->status, ['pendente_motorista', 'aguardando_finalizacao_trajeto'])) {
            throw ValidationException::withMessages(['status' => 'Esta solicitação já foi tratada.']);
        }

        $data = $r->validate(['km_saida' => 'nullable|integer|min:0']);

        $solicitacao = $this->service->motoristaAceitar($solicitacao, $user->motorista_id, $data['km_saida'] ?? null);

        return new SolicitacaoResource($solicitacao->load(['usuario', 'origemUnidade', 'destinoUnidade', 'viagem.motorista', 'viagem.veiculo', 'motoristaPendente', 'veiculoPendente']));
    }

    public function motoristaRecusar(Request $r, Solicitacao $solicitacao)
    {
        $user = $r->user();
        if (! $user->motorista_id || $solicitacao->motorista_pendente_id !== $user->motorista_id) {
            return response()->json(['error' => 'Esta viagem não está designada para você.'], 403);
        }

        if (! in_array($solicitacao->status, ['pendente_motorista', 'aguardando_finalizacao_trajeto'])) {
            throw ValidationException::withMessages(['status' => 'Esta solicitação já foi tratada.']);
        }

        $data = $r->validate(['motivo' => 'required|string|max:500']);

        $solicitacao = $this->service->motoristaRecusar($solicitacao, $user->motorista_id, $data['motivo']);

        return new SolicitacaoResource($solicitacao->load(['usuario', 'origemUnidade', 'destinoUnidade', 'viagem.motorista', 'viagem.veiculo', 'motoristaPendente', 'veiculoPendente']));
    }
```

- [ ] **Step 3: Rodar os testes**

Run: `php artisan test --filter=SolicitacaoApiTest`
Expected: PASS em todos os testes de `SolicitacaoApiTest`, incluindo os seis novos da Task 3.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Api/SolicitacaoController.php routes/api.php
git commit -m "feat: endpoints motorista-aceitar e motorista-recusar"
```

---

### Task 5: `SolicitacaoResource` — expor `motivo_recusa`

**Files:**
- Modify: `app/Http/Resources/SolicitacaoResource.php:23-25`
- Test: `tests/Feature/Solicitacao/SolicitacaoApiTest.php`

**Interfaces:**
- Produces: campo `motivo_recusa` no JSON de `SolicitacaoResource`.

- [ ] **Step 1: Escrever o teste**

Adicionar em `tests/Feature/Solicitacao/SolicitacaoApiTest.php`:

```php
    public function test_recurso_expoe_motivo_recusa(): void
    {
        $this->loginGestor();
        $solicitacao = Solicitacao::factory()->create([
            'status' => 'recusada',
            'motivo_recusa' => 'Sem combustível suficiente',
        ]);

        $this->getJson("/api/solicitacoes/{$solicitacao->id}")
            ->assertOk()
            ->assertJsonPath('data.motivo_recusa', 'Sem combustível suficiente');
    }
```

- [ ] **Step 2: Rodar o teste e confirmar falha**

Run: `php artisan test --filter=test_recurso_expoe_motivo_recusa`
Expected: FAIL — campo `motivo_recusa` ausente no JSON.

- [ ] **Step 3: Adicionar o campo no resource**

Em `app/Http/Resources/SolicitacaoResource.php`, logo após `'status' => $this->status,` (linha 23):

```php
            'status' => $this->status,
            'motivo_recusa' => $this->motivo_recusa,
```

- [ ] **Step 4: Rodar o teste**

Run: `php artisan test --filter=test_recurso_expoe_motivo_recusa`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Resources/SolicitacaoResource.php tests/Feature/Solicitacao/SolicitacaoApiTest.php
git commit -m "feat: expõe motivo_recusa no SolicitacaoResource"
```

---

### Task 6: `ViagemService::chegada` — fila só notifica, não cria viagem sozinha

**Files:**
- Modify: `tests/Feature/Solicitacao/SolicitacaoApiTest.php` (substituir `test_finalizar_trajeto_efetiva_solicitacao_pendente_com_troca_de_veiculo`)
- No changes needed in `app/Services/ViagemService.php` — a mudança de comportamento já veio de `processarPendentePara` (Task 3); esta tarefa só cobre o novo comportamento fim-a-fim com teste.

**Interfaces:**
- Consumes: `SolicitacaoService::processarPendentePara()` (Task 3), `SolicitacaoService::motoristaAceitar()` (Task 3).

- [ ] **Step 1: Substituir o teste antigo**

Em `tests/Feature/Solicitacao/SolicitacaoApiTest.php`, remover `test_finalizar_trajeto_efetiva_solicitacao_pendente_com_troca_de_veiculo` (linhas 183-232 do arquivo original) e adicionar:

```php
    public function test_conclusao_de_viagem_notifica_motorista_para_informar_km_da_fila_sem_criar_viagem(): void
    {
        Notification::fake();

        $motorista = Motorista::factory()->create();
        $usuarioMotorista = Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $motorista->id]);
        $veiculoAtual = Veiculo::factory()->create(['status' => 'em_uso', 'km_atual' => 1000]);
        $veiculoNovo = Veiculo::factory()->create(['status' => 'disponivel', 'km_atual' => 500]);

        Checkin::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoAtual->id,
            'km_saida' => 1000,
            'status' => 'ativo',
        ]);

        $viagemEmAndamento = Viagem::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoAtual->id,
            'km_saida' => 1000,
            'status' => 'em_andamento',
        ]);

        $solicitacaoNaFila = Solicitacao::factory()->create([
            'status' => 'aguardando_finalizacao_trajeto',
            'motorista_pendente_id' => $motorista->id,
            'veiculo_pendente_id' => $veiculoNovo->id,
        ]);

        $this->loginGestor();
        $this->patchJson("/api/viagens/{$viagemEmAndamento->id}/chegada", ['km_chegada' => 1050])
            ->assertOk();

        $this->assertDatabaseHas('solicitacoes', [
            'id' => $solicitacaoNaFila->id,
            'status' => 'aguardando_finalizacao_trajeto',
        ]);
        $this->assertDatabaseMissing('viagens', ['motorista_id' => $motorista->id, 'veiculo_id' => $veiculoNovo->id]);
        Notification::assertSentTo($usuarioMotorista, \App\Notifications\NovaViagemDesignada::class, function ($notification) use ($solicitacaoNaFila) {
            return $notification->toArray($usuarioMotorista)['solicitacao_id'] === $solicitacaoNaFila->id
                && $notification->toArray($usuarioMotorista)['fila'] === true;
        });

        $token = $usuarioMotorista->createToken('test')->plainTextToken;
        $this->withToken($token);

        $this->patchJson("/api/solicitacoes/{$solicitacaoNaFila->id}/motorista-aceitar", ['km_saida' => 1050])
            ->assertOk()
            ->assertJsonPath('data.status', 'em_trajeto');

        $this->assertDatabaseHas('viagens', [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoNovo->id,
            'km_saida' => 1050,
            'status' => 'em_andamento',
        ]);
        $this->assertDatabaseHas('checkins', [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoAtual->id,
            'status' => 'encerrado',
            'km_retorno' => 1050,
        ]);
    }
```

Ajustar o `use` no topo do arquivo para incluir `use Illuminate\Support\Facades\Notification;` (já presente, linha 13) — nenhuma mudança adicional de import necessária.

- [ ] **Step 2: Rodar o teste e confirmar que passa**

Run: `php artisan test --filter=SolicitacaoApiTest`
Expected: PASS em todos os testes do arquivo (a lógica já foi implementada nas Tasks 3-4; esta tarefa é puramente de cobertura fim-a-fim).

- [ ] **Step 3: Rodar a suíte completa para checar regressões em `ViagemApiTest`**

Run: `php artisan test --filter=Viagem`
Expected: PASS — nenhum teste de `ViagemApiTest`/`ViagemPontoApiTest` depende do comportamento antigo de `processarPendentePara`.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Solicitacao/SolicitacaoApiTest.php
git commit -m "test: cobre fila de viagens fim-a-fim sem criação automática"
```

---

### Task 7: Frontend — funções de API para aceite/recusa do motorista

**Files:**
- Modify: `resources/js/api/solicitacoes.js`

**Interfaces:**
- Produces: `motoristaAceitar(id, kmSaida)`, `motoristaRecusar(id, motivo)`.

- [ ] **Step 1: Adicionar as funções**

```js
import api from './axios'

export const listar = (params) => api.get('/solicitacoes', { params })
export const buscar = (id) => api.get(`/solicitacoes/${id}`)
export const aceitar = (id, data) => api.patch(`/solicitacoes/${id}/aceitar`, data)
export const cancelar = (id) => api.patch(`/solicitacoes/${id}/cancelar`)
export const motoristaAceitar = (id, kmSaida) => api.patch(`/solicitacoes/${id}/motorista-aceitar`, kmSaida != null ? { km_saida: kmSaida } : {})
export const motoristaRecusar = (id, motivo) => api.patch(`/solicitacoes/${id}/motorista-recusar`, { motivo })
```

- [ ] **Step 2: Verificar build**

Run: `npm run build`
Expected: build conclui sem erro.

- [ ] **Step 3: Commit**

```bash
git add resources/js/api/solicitacoes.js
git commit -m "feat: funções de API para aceite/recusa de viagem pelo motorista"
```

---

### Task 8: `useNotificacoes` — habilitar para o motorista e distinguir tipo

**Files:**
- Modify: `resources/js/hooks/useNotificacoes.js:56`

**Interfaces:**
- Consumes: `user.perfil`, `user.motorista_id` (contexto `useAuth()`).
- Produces: `pendentes` agora inclui notificações do tipo `App\Notifications\NovaViagemDesignada` para usuários `operador` com `motorista_id`, além das já existentes para `admin`/`gestor`. Cada item de `pendentes`/`notificacoes` mantém o campo `type` já retornado pelo Laravel (FQCN da notificação), usado pelos componentes consumidores para decidir qual pop-up renderizar.

- [ ] **Step 1: Alterar a condição `habilitado`**

Em `resources/js/hooks/useNotificacoes.js`, linha 56:

```js
  const habilitado = user?.perfil === 'admin' || user?.perfil === 'gestor' || (user?.perfil === 'operador' && !!user?.motorista_id)
```

- [ ] **Step 2: Verificar build**

Run: `npm run build`
Expected: build conclui sem erro.

- [ ] **Step 3: Commit**

```bash
git add resources/js/hooks/useNotificacoes.js
git commit -m "feat: habilita polling de notificações para motoristas"
```

---

### Task 9: Componentes do pop-up de viagem designada (motorista)

**Files:**
- Create: `resources/js/components/notificacoes/NovaViagemDesignadaPopup.jsx`
- Create: `resources/js/components/notificacoes/KmSaidaModal.jsx`
- Create: `resources/js/components/notificacoes/MotivoRecusaModal.jsx`

**Interfaces:**
- Consumes: `motoristaAceitar(id, kmSaida)`, `motoristaRecusar(id, motivo)` (`resources/js/api/solicitacoes.js`, Task 7).
- Consumes: query `viagens` ativa do motorista — reaproveita `GET /api/viagens?status=em_andamento` já usado em `ViagensList.jsx` (ver Task 12) para decidir se abre `KmSaidaModal` direto ou mostra "entrou na fila".
- Produces: `NovaViagemDesignadaPopup({ notificacao, temViagemAtiva, onFechar })` — componente default export.

- [ ] **Step 1: Criar `KmSaidaModal.jsx`**

```jsx
import { useState } from 'react'

export default function KmSaidaModal({ onConfirmar, onCancelar, loading }) {
  const [km, setKm] = useState('')
  const [erro, setErro] = useState('')

  const submeter = (e) => {
    e.preventDefault()
    const valor = Number(km)
    if (!km || Number.isNaN(valor) || valor < 0) {
      setErro('Informe um KM válido.')
      return
    }
    onConfirmar(valor)
  }

  return (
    <div className="fixed inset-0 z-[120] flex items-center justify-center bg-black/40">
      <form onSubmit={submeter} className="bg-white rounded-2xl shadow-xl p-6 w-[calc(100%-2rem)] max-w-sm">
        <h2 className="text-base font-semibold text-navy-900 mb-1">Informe o KM de saída</h2>
        <p className="text-sm text-gray-600 mb-4">Necessário para iniciar a viagem.</p>
        <input
          type="number"
          inputMode="numeric"
          min="0"
          autoFocus
          value={km}
          onChange={e => { setKm(e.target.value); setErro('') }}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
          placeholder="Ex: 12345"
        />
        {erro && <p className="text-xs text-red-600 mt-1">{erro}</p>}
        <div className="flex gap-2 mt-5">
          {onCancelar && (
            <button type="button" onClick={onCancelar} className="flex-1 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">
              Cancelar
            </button>
          )}
          <button type="submit" disabled={loading} className="flex-1 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 disabled:opacity-60">
            {loading ? 'Iniciando...' : 'Iniciar viagem'}
          </button>
        </div>
      </form>
    </div>
  )
}
```

- [ ] **Step 2: Criar `MotivoRecusaModal.jsx`**

```jsx
import { useState } from 'react'

export default function MotivoRecusaModal({ onConfirmar, onCancelar, loading }) {
  const [motivo, setMotivo] = useState('')
  const [erro, setErro] = useState('')

  const submeter = (e) => {
    e.preventDefault()
    if (!motivo.trim()) {
      setErro('Informe o motivo da recusa.')
      return
    }
    onConfirmar(motivo.trim())
  }

  return (
    <div className="fixed inset-0 z-[120] flex items-center justify-center bg-black/40">
      <form onSubmit={submeter} className="bg-white rounded-2xl shadow-xl p-6 w-[calc(100%-2rem)] max-w-sm">
        <h2 className="text-base font-semibold text-navy-900 mb-1">Motivo da recusa</h2>
        <textarea
          autoFocus
          rows={4}
          value={motivo}
          onChange={e => { setMotivo(e.target.value); setErro('') }}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
          placeholder="Descreva o motivo..."
        />
        {erro && <p className="text-xs text-red-600 mt-1">{erro}</p>}
        <div className="flex gap-2 mt-5">
          <button type="button" onClick={onCancelar} className="flex-1 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">
            Voltar
          </button>
          <button type="submit" disabled={loading} className="flex-1 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700 disabled:opacity-60">
            {loading ? 'Enviando...' : 'Confirmar recusa'}
          </button>
        </div>
      </form>
    </div>
  )
}
```

- [ ] **Step 3: Criar `NovaViagemDesignadaPopup.jsx`**

```jsx
import { useState } from 'react'
import { createPortal } from 'react-dom'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Truck } from 'lucide-react'
import * as solicitacoesApi from '../../api/solicitacoes'
import KmSaidaModal from './KmSaidaModal'
import MotivoRecusaModal from './MotivoRecusaModal'

export default function NovaViagemDesignadaPopup({ notificacao, temViagemAtiva, onFechar }) {
  const qc = useQueryClient()
  const [tela, setTela] = useState('inicial')
  const ehFila = !!notificacao.data?.fila

  const aceitarMutation = useMutation({
    mutationFn: (kmSaida) => solicitacoesApi.motoristaAceitar(notificacao.data.solicitacao_id, kmSaida),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['viagens'] })
      onFechar()
    },
  })

  const recusarMutation = useMutation({
    mutationFn: (motivo) => solicitacoesApi.motoristaRecusar(notificacao.data.solicitacao_id, motivo),
    onSuccess: onFechar,
  })

  if (tela === 'km' || (ehFila && tela !== 'recusar')) {
    return (
      <KmSaidaModal
        loading={aceitarMutation.isPending}
        onCancelar={ehFila ? undefined : () => setTela('inicial')}
        onConfirmar={(km) => aceitarMutation.mutate(km)}
      />
    )
  }

  if (tela === 'recusar') {
    return (
      <MotivoRecusaModal
        loading={recusarMutation.isPending}
        onCancelar={() => setTela('inicial')}
        onConfirmar={(motivo) => recusarMutation.mutate(motivo)}
      />
    )
  }

  const detalhe = notificacao.data?.detalhe

  return createPortal(
    <>
      <div className="fixed inset-0 bg-black/40 z-[100]" />
      <div className="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] w-[calc(100%-2rem)] max-w-sm">
        <div className="bg-white rounded-2xl shadow-xl p-6 text-center">
          <div className="mx-auto mb-4 w-12 h-12 rounded-full bg-brand-100 text-brand-600 flex items-center justify-center">
            <Truck size={24} />
          </div>
          <h2 className="text-base font-semibold text-navy-900 mb-1">Nova Viagem Designada pelo Gestor</h2>
          {detalhe && <p className="text-sm text-gray-500 mt-1">{detalhe}</p>}
          <div className="flex gap-2 mt-5">
            <button
              onClick={() => setTela('recusar')}
              className="flex-1 py-2 rounded-lg border border-red-300 text-red-600 text-sm font-medium hover:bg-red-50"
            >
              Recusar
            </button>
            <button
              onClick={() => setTela(temViagemAtiva ? 'fila' : 'km')}
              className="flex-1 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700"
            >
              Aceitar
            </button>
          </div>
        </div>
      </div>
    </>,
    document.body
  )
}
```

Nota sobre o estado `'fila'`: quando `temViagemAtiva` é verdadeiro e o motorista clica em Aceitar, chama `aceitarMutation.mutate(undefined)` diretamente (sem passar por `KmSaidaModal`) — ajustar o botão Aceitar para, nesse caso, disparar a mutation em vez de trocar de tela:

```jsx
            <button
              onClick={() => temViagemAtiva ? aceitarMutation.mutate(undefined) : setTela('km')}
              disabled={aceitarMutation.isPending}
              className="flex-1 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 disabled:opacity-60"
            >
              {aceitarMutation.isPending ? 'Confirmando...' : 'Aceitar'}
            </button>
```

(Substituir o bloco de botões do Step 3 por esta versão final antes de prosseguir.)

- [ ] **Step 4: Verificar build**

Run: `npm run build`
Expected: build conclui sem erro.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/notificacoes/NovaViagemDesignadaPopup.jsx resources/js/components/notificacoes/KmSaidaModal.jsx resources/js/components/notificacoes/MotivoRecusaModal.jsx
git commit -m "feat: componentes de aceite/recusa de viagem designada"
```

---

### Task 10: `Header.jsx` — renderizar o pop-up certo por tipo de notificação e checar viagem ativa

**Files:**
- Modify: `resources/js/components/layout/Header.jsx`

**Interfaces:**
- Consumes: `NovaViagemDesignadaPopup` (Task 9), `useNotificacoes()` (Task 8) — `pendentes[i].type` (FQCN retornado pelo Laravel, ex.: `App\Notifications\NovaViagemDesignada`).
- Consumes: `viagensApi.listar({ status: 'em_andamento' })` — mesma API já usada por `ViagensList.jsx` (ver Task 12), para saber se o motorista tem viagem ativa antes de decidir o fluxo do Aceitar.

- [ ] **Step 1: Importar o novo componente e a query de viagem ativa**

No topo de `resources/js/components/layout/Header.jsx`, adicionar:

```jsx
import { useQuery } from '@tanstack/react-query'
import NovaViagemDesignadaPopup from '../notificacoes/NovaViagemDesignadaPopup'
import * as viagensApi from '../../api/viagens'
```

(Verificar se `resources/js/api/viagens.js` já expõe uma função `listar(params)` — reaproveitar; se o nome for outro, usar o existente.)

- [ ] **Step 2: Buscar a viagem ativa do motorista quando aplicável**

Dentro do componente, após a linha `const { total, notificacoes, pendentes, removerPendente } = useNotificacoes()`:

```jsx
  const ehMotorista = user?.perfil === 'operador' && !!user?.motorista_id
  const { data: viagensAtivas } = useQuery({
    queryKey: ['viagens', 'ativa-motorista'],
    queryFn: () => viagensApi.listar({ status: 'em_andamento' }).then(r => r.data.data ?? r.data),
    enabled: ehMotorista,
  })
  const temViagemAtiva = (viagensAtivas ?? []).length > 0
```

- [ ] **Step 3: Renderizar condicionalmente pelo tipo, no lugar do `NovaSolicitacaoPopup` fixo (linhas 97-103 do arquivo atual)**

```jsx
      {pendentes[0] && pendentes[0].type?.endsWith('NovaViagemDesignada') && (
        <NovaViagemDesignadaPopup
          key={pendentes[0].id}
          notificacao={pendentes[0]}
          temViagemAtiva={temViagemAtiva}
          onFechar={() => removerPendente(pendentes[0].id)}
        />
      )}
      {pendentes[0] && !pendentes[0].type?.endsWith('NovaViagemDesignada') && (
        <NovaSolicitacaoPopup
          key={pendentes[0].id}
          notificacao={pendentes[0]}
          onFechar={() => removerPendente(pendentes[0].id)}
        />
      )}
```

- [ ] **Step 4: Verificar build**

Run: `npm run build`
Expected: build conclui sem erro.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/layout/Header.jsx
git commit -m "feat: Header renderiza pop-up de viagem designada para motoristas"
```

---

### Task 11: Tela do gestor — badge "Recusada pelo motorista" e redesignação

**Files:**
- Modify: `resources/js/pages/solicitacoes/SolicitacoesList.jsx`

**Interfaces:**
- Consumes: campo `motivo_recusa` de `SolicitacaoResource` (Task 5), já incluso na resposta de `GET /api/solicitacoes`.
- Consumes: o mesmo modal de aceite já existente (`aceitarTarget`/`aceitarForm`, linhas 124-155) — reaberto a partir de uma solicitação `recusada` sem mudanças estruturais, pois `SolicitacaoController::aceitar` já aceita esse status (Task 3 não bloqueia `recusada`).

- [ ] **Step 1: Localizar a renderização de status/badge na tabela**

Ler `resources/js/pages/solicitacoes/SolicitacoesList.jsx` linhas 80-116 (bloco da tabela) antes de editar, para reaproveitar as classes de badge já usadas para outros status.

- [ ] **Step 2: Adicionar badge de recusa e motivo**

Localizar a célula que renderiza `s.status` na tabela e adicionar, logo abaixo do badge de status existente, quando `s.status === 'recusada'`:

```jsx
              {s.status === 'recusada' && (
                <p className="text-xs text-red-600 mt-1" title={s.motivo_recusa}>
                  Recusada: {s.motivo_recusa}
                </p>
              )}
```

- [ ] **Step 3: Permitir reabrir o modal de aceite a partir de `recusada`**

Localizar o botão/ação que abre `setAceitarTarget(s)` (disponível hoje só para `status === 'aberto'`) e ampliar a condição para incluir `recusada`:

```jsx
              {(s.status === 'aberto' || s.status === 'recusada') && (
                <button onClick={() => setAceitarTarget(s)} className="text-brand-600 hover:underline text-xs font-medium">
                  {s.status === 'recusada' ? 'Redesignar' : 'Aceitar'}
                </button>
              )}
```

- [ ] **Step 4: Verificar build**

Run: `npm run build`
Expected: build conclui sem erro.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/solicitacoes/SolicitacoesList.jsx
git commit -m "feat: gestor visualiza motivo de recusa e redesigna solicitação"
```

---

### Task 12: Indicador de fila para o motorista

**Files:**
- Modify: `resources/js/pages/viagens/ViagemDetalhe.jsx`

**Interfaces:**
- Consumes: `GET /api/solicitacoes?status=aguardando_finalizacao_trajeto` (endpoint `index` já existente, `SolicitacaoController::index`, filtra por `usuario_id` automaticamente para perfil `operador`/`solicitante` — mas a fila é por `motorista_pendente_id`, não `usuario_id`; usar o filtro genérico e comparar no client, ou adicionar `?motorista_id=` se já suportado. Verificar `SolicitacaoController::index` antes de implementar — hoje só filtra por `unidade_id`/`status` além do escopo por usuário).

- [ ] **Step 1: Ler o estado atual de `ViagemDetalhe.jsx` e do filtro de `SolicitacaoController::index`**

Antes de editar, confirmar como a tela busca dados do motorista autenticado (provavelmente via `viagem.motorista_id === user.motorista_id`) e se `SolicitacaoController::index` precisa de um filtro adicional por `motorista_pendente_id` para o operador enxergar sua própria fila (hoje o filtro por usuário restringe a `usuario_id`, que é o solicitante, não o motorista — operador motorista não é o `usuario_id` da solicitação).

- [ ] **Step 2: Adicionar filtro por motorista no `SolicitacaoController::index`**

Em `app/Http/Controllers/Api/SolicitacaoController.php`, no método `index`, adicionar suporte a `?motorista_pendente_id=` (o próprio motorista só pode consultar o seu, validado pelo escopo do próprio usuário autenticado):

```php
    public function index(Request $r)
    {
        $user = $r->user();

        $unidadeFiltro = in_array($user->perfil, ['admin', 'gestor']) ? $r->query('unidade_id') : null;

        $solicitacoes = Solicitacao::with(['usuario', 'origemUnidade', 'destinoUnidade', 'viagem.motorista', 'viagem.veiculo', 'motoristaPendente', 'veiculoPendente'])
            ->when(in_array($user->perfil, ['operador', 'solicitante']) && ! $user->motorista_id, fn ($q) => $q->where('usuario_id', $user->id))
            ->when($user->perfil === 'operador' && $user->motorista_id, fn ($q) => $q->where('motorista_pendente_id', $user->motorista_id))
            ->when($unidadeFiltro, fn ($q) => $q->where('unidade_id', $unidadeFiltro))
            ->when($r->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->limit(200)
            ->get();

        return SolicitacaoResource::collection($solicitacoes);
    }
```

- [ ] **Step 3: Escrever teste do novo filtro**

Adicionar em `tests/Feature/Solicitacao/SolicitacaoApiTest.php`:

```php
    public function test_motorista_ve_apenas_solicitacoes_designadas_para_ele(): void
    {
        $motorista = Motorista::factory()->create();
        $usuarioMotorista = Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $motorista->id]);
        Solicitacao::factory()->create(['status' => 'pendente_motorista', 'motorista_pendente_id' => $motorista->id]);
        Solicitacao::factory()->create(['status' => 'pendente_motorista', 'motorista_pendente_id' => Motorista::factory()->create()->id]);

        $token = $usuarioMotorista->createToken('test')->plainTextToken;
        $this->withToken($token);

        $this->getJson('/api/solicitacoes')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
```

- [ ] **Step 4: Rodar o teste**

Run: `php artisan test --filter=SolicitacaoApiTest`
Expected: PASS em todos os testes, incluindo o novo.

- [ ] **Step 5: Adicionar o indicador visual em `ViagemDetalhe.jsx`**

Ler o componente para localizar onde a viagem `em_andamento` é exibida, e adicionar logo abaixo do cabeçalho principal:

```jsx
  const { data: fila } = useQuery({
    queryKey: ['solicitacoes', 'fila-motorista'],
    queryFn: () => solicitacoesApi.listar({ status: 'aguardando_finalizacao_trajeto' }).then(r => r.data.data ?? r.data),
    enabled: viagem?.status === 'em_andamento',
    refetchInterval: 30_000,
  })
```

(usar o `import * as solicitacoesApi from '../../api/solicitacoes'` — adicionar se ainda não existir no arquivo) e, no JSX, próximo ao topo da tela:

```jsx
      {(fila ?? []).length > 0 && (
        <div className="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg px-4 py-2 mb-4">
          Próxima viagem aguardando ({fila.length} na fila)
        </div>
      )}
```

- [ ] **Step 6: Verificar build**

Run: `npm run build`
Expected: build conclui sem erro.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/SolicitacaoController.php resources/js/pages/viagens/ViagemDetalhe.jsx tests/Feature/Solicitacao/SolicitacaoApiTest.php
git commit -m "feat: indicador de fila de viagens para o motorista"
```

---

### Task 13: Suíte completa e regressão

**Files:**
- None (apenas verificação)

- [ ] **Step 1: Rodar toda a suíte PHPUnit**

Run: `php artisan test`
Expected: PASS em todos os testes (incluindo `SolicitacaoApiTest`, `ViagemApiTest`, `NotificacaoApiTest` e os demais não relacionados, que não devem ter sido afetados).

- [ ] **Step 2: Rodar Pint**

Run: `./vendor/bin/pint`
Expected: sem alterações pendentes (ou aplica formatação automaticamente — rodar de novo e comitar se necessário).

- [ ] **Step 3: Build final do frontend**

Run: `npm run build`
Expected: build conclui sem erro, `public/build/` atualizado.

- [ ] **Step 4: Commit final (se Pint alterou algo)**

```bash
git add -A
git commit -m "style: aplica pint após implementação de designação de viagem"
```
