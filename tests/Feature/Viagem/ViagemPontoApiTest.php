<?php

namespace Tests\Feature\Viagem;

use App\Models\Viagem;
use Tests\TestCase;

class ViagemPontoApiTest extends TestCase
{
    public function test_registra_ponto_gps_em_viagem_em_andamento(): void
    {
        $this->loginGestor();
        $viagem = Viagem::factory()->create(['status' => 'em_andamento']);

        $this->postJson("/api/viagens/{$viagem->id}/pontos", [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ])->assertCreated();

        $this->assertDatabaseHas('viagem_pontos', ['viagem_id' => $viagem->id]);
    }

    public function test_nao_registra_ponto_em_viagem_concluida(): void
    {
        $this->loginGestor();
        $viagem = Viagem::factory()->concluida()->create();

        $this->postJson("/api/viagens/{$viagem->id}/pontos", [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ])->assertStatus(422);
    }

    public function test_reenvio_do_mesmo_ponto_capturado_at_nao_duplica(): void
    {
        $this->loginGestor();
        $viagem = Viagem::factory()->create(['status' => 'em_andamento']);
        $capturadoAt = now()->toISOString();

        $this->postJson("/api/viagens/{$viagem->id}/pontos", [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
            'capturado_at' => $capturadoAt,
        ])->assertCreated();

        $this->postJson("/api/viagens/{$viagem->id}/pontos", [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
            'capturado_at' => $capturadoAt,
        ])->assertOk();

        $this->assertDatabaseCount('viagem_pontos', 1);
    }

    public function test_lista_pontos_da_viagem(): void
    {
        $this->loginGestor();
        $viagem = Viagem::factory()->create(['status' => 'em_andamento']);

        $this->postJson("/api/viagens/{$viagem->id}/pontos", ['latitude' => -23.5505, 'longitude' => -46.6333]);
        $this->postJson("/api/viagens/{$viagem->id}/pontos", ['latitude' => -23.5510, 'longitude' => -46.6340]);

        $this->getJson("/api/viagens/{$viagem->id}/pontos")
            ->assertOk()
            ->assertJsonCount(2);
    }
}
