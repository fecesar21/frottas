<?php

namespace Tests\Feature\Notificacao;

use App\Models\Motorista;
use App\Notifications\CnhVencendoNotification;
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
}
