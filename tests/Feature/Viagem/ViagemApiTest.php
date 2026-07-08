<?php

namespace Tests\Feature\Viagem;

use App\Models\Checkin;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Models\Viagem;
use Tests\TestCase;

class ViagemApiTest extends TestCase
{
    public function test_lista_viagens(): void
    {
        $this->loginAdmin();
        Viagem::factory()->count(3)->create();

        $this->getJson('/api/viagens')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'origem', 'destino', 'status']]]);
    }

    public function test_cria_viagem(): void
    {
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create(['km_atual' => 4000]);
        $motorista = Motorista::factory()->create();

        $response = $this->postJson('/api/viagens', [
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'origem' => 'São Paulo',
            'destino' => 'Campinas',
            'km_saida' => 5000,
        ]);

        $response->assertCreated()->assertJsonPath('data.origem', 'São Paulo');
        $this->assertDatabaseHas('viagens', ['origem' => 'São Paulo', 'destino' => 'Campinas']);
    }

    public function test_operador_sem_checkin_ativo_recebe_403_ao_criar_viagem(): void
    {
        $usuario = $this->loginOperador();
        $motorista = Motorista::factory()->create();
        $usuario->update(['motorista_id' => $motorista->id]);
        $veiculo = Veiculo::factory()->create();

        $this->postJson('/api/viagens', [
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'origem' => 'São Paulo',
            'destino' => 'Campinas',
            'km_saida' => 5000,
        ])->assertForbidden();
    }

    public function test_operador_com_checkin_ativo_cria_viagem_vinculada_ao_veiculo_do_checkin(): void
    {
        $usuario = $this->loginOperador();
        $veiculo = Veiculo::factory()->create(['km_atual' => 4000]);
        $motorista = Motorista::factory()->create();
        $usuario->update(['motorista_id' => $motorista->id]);

        $checkin = Checkin::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
            'status' => 'ativo',
        ]);

        // veiculo_id/motorista_id são exigidos pela validação da request, mas o
        // controller os sobrescreve com os dados do check-in ativo do operador.
        $response = $this->postJson('/api/viagens', [
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'origem' => 'São Paulo',
            'destino' => 'Campinas',
            'km_saida' => 5000,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('viagens', [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
            'checkin_id' => $checkin->id,
        ]);
    }

    public function test_registra_chegada_da_viagem(): void
    {
        $this->loginGestor();
        $viagem = Viagem::factory()->create(['km_saida' => 5000]);

        $this->patchJson("/api/viagens/{$viagem->id}/chegada", ['km_chegada' => 5120])
            ->assertOk()
            ->assertJsonPath('data.status', 'concluida');

        $this->assertDatabaseHas('viagens', ['id' => $viagem->id, 'km_chegada' => 5120, 'status' => 'concluida']);
    }
}
