<?php

namespace Tests\Feature\Notificacao;

use App\Models\Motorista;
use App\Models\Solicitacao;
use App\Models\Usuario;
use App\Notifications\CnhVencendoNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificacaoApiTest extends TestCase
{
    public function test_lista_notificacoes_do_usuario_autenticado(): void
    {
        $usuario = $this->loginAdmin();
        $motorista = Motorista::factory()->cnhVencendo()->create();

        $usuario->notify(new CnhVencendoNotification($motorista));

        $this->getJson('/api/notificacoes')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_marca_notificacao_como_lida(): void
    {
        $usuario = $this->loginAdmin();
        $motorista = Motorista::factory()->cnhVencendo()->create();

        $usuario->notify(new CnhVencendoNotification($motorista));
        $notificacaoId = $usuario->notifications()->first()->id;

        $this->patchJson("/api/notificacoes/{$notificacaoId}/lida")->assertOk();

        $this->assertNotNull($usuario->notifications()->find($notificacaoId)->read_at);
    }

    public function test_lista_notificacoes_nao_lidas_com_total(): void
    {
        $usuario = $this->loginAdmin();
        $motorista = Motorista::factory()->cnhVencendo()->create();

        $usuario->notify(new CnhVencendoNotification($motorista));
        $usuario->notify(new CnhVencendoNotification($motorista));

        $this->getJson('/api/notificacoes/nao-lidas')
            ->assertOk()
            ->assertJson(['total' => 2])
            ->assertJsonCount(2, 'notificacoes');
    }

    public function test_marca_todas_notificacoes_como_lidas(): void
    {
        $usuario = $this->loginAdmin();
        $motorista = Motorista::factory()->cnhVencendo()->create();

        $usuario->notify(new CnhVencendoNotification($motorista));
        $usuario->notify(new CnhVencendoNotification($motorista));

        $this->postJson('/api/notificacoes/marcar-lidas')->assertNoContent();

        $this->assertSame(0, $usuario->unreadNotifications()->count());
    }

    public function test_nao_lidas_descarta_notificacao_de_solicitacao_inexistente(): void
    {
        $usuario = $this->loginAdmin();
        $usuario->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\NovaSolicitacaoDisponivel',
            'data' => ['solicitacao_id' => (string) Str::uuid()],
        ]);

        $this->getJson('/api/notificacoes/nao-lidas')
            ->assertOk()
            ->assertJson(['total' => 0])
            ->assertJsonCount(0, 'notificacoes');

        $this->assertSame(0, $usuario->unreadNotifications()->count());
    }

    private function notificarDesignacao(Usuario $usuario, Solicitacao $solicitacao, bool $fila = false): void
    {
        $usuario->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\NovaViagemDesignada',
            'data' => ['solicitacao_id' => $solicitacao->id, 'fila' => $fila],
        ]);
    }

    public function test_nao_lidas_mantem_designacao_aguardando_resposta_do_motorista(): void
    {
        $motorista = Motorista::factory()->create();
        $usuario = $this->loginOperador();
        $usuario->update(['motorista_id' => $motorista->id]);
        $solicitacao = Solicitacao::factory()->create([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motorista->id,
        ]);
        $this->notificarDesignacao($usuario, $solicitacao);

        $this->getJson('/api/notificacoes/nao-lidas')->assertOk()->assertJson(['total' => 1]);
    }

    public function test_nao_lidas_descarta_designacao_ja_respondida(): void
    {
        $motorista = Motorista::factory()->create();
        $usuario = $this->loginOperador();
        $usuario->update(['motorista_id' => $motorista->id]);
        $recusada = Solicitacao::factory()->create(['status' => 'recusada', 'motorista_pendente_id' => $motorista->id]);
        $naFila = Solicitacao::factory()->create(['status' => 'aguardando_finalizacao_trajeto', 'motorista_pendente_id' => $motorista->id]);
        $outroMotorista = Solicitacao::factory()->create([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => Motorista::factory()->create()->id,
        ]);
        $this->notificarDesignacao($usuario, $recusada);
        $this->notificarDesignacao($usuario, $naFila);
        $this->notificarDesignacao($usuario, $outroMotorista);

        $this->getJson('/api/notificacoes/nao-lidas')->assertOk()->assertJson(['total' => 0]);
        $this->assertSame(0, $usuario->unreadNotifications()->count());
    }

    public function test_nao_lidas_mantem_designacao_da_fila_ate_o_aceite(): void
    {
        $motorista = Motorista::factory()->create();
        $usuario = $this->loginOperador();
        $usuario->update(['motorista_id' => $motorista->id]);
        $solicitacao = Solicitacao::factory()->create([
            'status' => 'aguardando_finalizacao_trajeto',
            'motorista_pendente_id' => $motorista->id,
        ]);
        $this->notificarDesignacao($usuario, $solicitacao, fila: true);

        $this->getJson('/api/notificacoes/nao-lidas')->assertOk()->assertJson(['total' => 1]);
    }

    public function test_nao_lidas_descarta_solicitacao_disponivel_ja_assumida(): void
    {
        $usuario = $this->loginOperador();
        $solicitacao = Solicitacao::factory()->create(['status' => 'pendente_motorista']);
        $usuario->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\NovaSolicitacaoDisponivel',
            'data' => ['solicitacao_id' => $solicitacao->id],
        ]);

        $this->getJson('/api/notificacoes/nao-lidas')->assertOk()->assertJson(['total' => 0]);
    }
}
