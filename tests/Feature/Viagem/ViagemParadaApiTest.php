<?php

namespace Tests\Feature\Viagem;

use App\Models\Viagem;
use App\Models\ViagemParada;
use Tests\TestCase;

class ViagemParadaApiTest extends TestCase
{
    public function test_registra_parada_em_viagem_em_andamento(): void
    {
        $this->loginGestor();
        $viagem = Viagem::factory()->create(['status' => 'em_andamento']);

        $this->postJson("/api/viagens/{$viagem->id}/paradas", ['local' => 'Farmácia central', 'motivo' => 'Buscar medicação'])
            ->assertCreated()
            ->assertJsonPath('local', 'FARMÁCIA CENTRAL')
            ->assertJsonPath('ordem', 1);

        $this->getJson("/api/viagens/{$viagem->id}")
            ->assertJsonPath('data.paradas.0.motivo', 'Buscar medicação');
    }

    public function test_nao_permite_segunda_parada_aberta(): void
    {
        $this->loginGestor();
        $viagem = Viagem::factory()->create(['status' => 'em_andamento']);

        $this->postJson("/api/viagens/{$viagem->id}/paradas", ['local' => 'A'])->assertCreated();
        $this->postJson("/api/viagens/{$viagem->id}/paradas", ['local' => 'B'])->assertStatus(422);
    }

    public function test_nao_registra_parada_em_viagem_concluida(): void
    {
        $this->loginGestor();
        $viagem = Viagem::factory()->concluida()->create();

        $this->postJson("/api/viagens/{$viagem->id}/paradas", ['local' => 'A'])->assertStatus(422);
    }

    public function test_retomar_encerra_parada_e_libera_nova(): void
    {
        $this->loginGestor();
        $viagem = Viagem::factory()->create(['status' => 'em_andamento']);
        $id = $this->postJson("/api/viagens/{$viagem->id}/paradas", ['local' => 'A'])->json('id');

        $this->patchJson("/api/viagens/{$viagem->id}/paradas/{$id}/retomar")->assertOk();
        $this->assertNotNull(ViagemParada::find($id)->saida_at);
        $this->patchJson("/api/viagens/{$viagem->id}/paradas/{$id}/retomar")->assertStatus(422);

        $this->postJson("/api/viagens/{$viagem->id}/paradas", ['local' => 'B'])
            ->assertCreated()
            ->assertJsonPath('ordem', 2);
    }

    public function test_motorista_nao_registra_parada_em_viagem_de_outro(): void
    {
        $this->loginOperador();
        $viagem = Viagem::factory()->create(['status' => 'em_andamento']);

        $this->postJson("/api/viagens/{$viagem->id}/paradas", ['local' => 'A'])->assertForbidden();
    }

    public function test_chegada_encerra_parada_aberta(): void
    {
        $this->loginGestor();
        $viagem = Viagem::factory()->create(['status' => 'em_andamento', 'km_saida' => 1000]);
        $id = $this->postJson("/api/viagens/{$viagem->id}/paradas", ['local' => 'A'])->json('id');

        $this->patchJson("/api/viagens/{$viagem->id}/chegada", ['km_chegada' => 1010])->assertOk();

        $this->assertNotNull(ViagemParada::find($id)->saida_at);
    }
}
