<?php

namespace Tests\Feature\Notificacao;

use App\Models\Solicitacao;
use App\Models\Usuario;
use App\Notifications\NovaSolicitacaoDisponivel;
use App\Notifications\NovaViagemDesignada;
use Laravel\Sanctum\Sanctum;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class PushInscricaoTest extends TestCase
{
    private function payload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'chave-p256dh', 'auth' => 'chave-auth']];
    }

    public function test_motorista_registra_e_remove_inscricao_do_aparelho(): void
    {
        $motorista = Usuario::factory()->create(['perfil' => 'operador']);
        Sanctum::actingAs($motorista);

        $this->postJson('/api/push/inscricao', $this->payload())->assertCreated();
        $this->assertSame(1, $motorista->pushSubscriptions()->count());

        $this->deleteJson('/api/push/inscricao', ['endpoint' => $this->payload()['endpoint']])->assertNoContent();
        $this->assertSame(0, $motorista->pushSubscriptions()->count());
    }

    public function test_aparelho_compartilhado_passa_para_o_novo_usuario(): void
    {
        $anterior = Usuario::factory()->create(['perfil' => 'operador']);
        $novo = Usuario::factory()->create(['perfil' => 'operador']);

        Sanctum::actingAs($anterior);
        $this->postJson('/api/push/inscricao', $this->payload())->assertCreated();

        Sanctum::actingAs($novo);
        $this->postJson('/api/push/inscricao', $this->payload())->assertCreated();

        $this->assertSame(1, PushSubscription::count());
        $this->assertSame(0, $anterior->pushSubscriptions()->count());
        $this->assertSame(1, $novo->pushSubscriptions()->count());
    }

    public function test_inscricao_invalida_e_rejeitada(): void
    {
        Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'operador']));

        $this->postJson('/api/push/inscricao', ['endpoint' => 'nao-e-url'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    }

    public function test_chave_publica_exposta_e_rota_exige_login(): void
    {
        config(['webpush.vapid.public_key' => 'CHAVE-PUBLICA']);
        $this->getJson('/api/push/chave-publica')->assertUnauthorized();

        Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'operador']));
        $this->getJson('/api/push/chave-publica')->assertOk()->assertJsonPath('chave', 'CHAVE-PUBLICA');
    }

    public function test_notificacoes_ao_motorista_tambem_vao_por_push(): void
    {
        $s = Solicitacao::factory()->create();
        $u = Usuario::factory()->create(['perfil' => 'operador']);

        foreach ([new NovaSolicitacaoDisponivel($s), new NovaViagemDesignada($s)] as $notificacao) {
            $this->assertContains(WebPushChannel::class, $notificacao->via($u));

            $push = $notificacao->toWebPush($u, $notificacao)->toArray();
            $this->assertNotEmpty($push['title']);
            $this->assertStringContainsString('—', $push['body']);
            $this->assertSame($s->id, $push['data']['solicitacao_id']);
        }
    }
}
