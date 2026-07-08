<?php

namespace Tests\Feature\Km;

use App\Models\KmRegistro;
use App\Models\Motorista;
use App\Models\Veiculo;
use Tests\TestCase;

class KmApiTest extends TestCase
{
    public function test_lista_registros_km(): void
    {
        $this->loginAdmin();
        KmRegistro::factory()->count(3)->create();

        $this->getJson('/api/km')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'km_anterior', 'km_atual']]]);
    }

    public function test_cria_registro_km(): void
    {
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create(['km_atual' => 5000]);
        $motorista = Motorista::factory()->create();

        $response = $this->postJson('/api/km', [
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'km_atual' => 5150,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('km_registros', [
            'veiculo_id' => $veiculo->id,
            'km_anterior' => 5000,
            'km_atual' => 5150,
        ]);
        $this->assertDatabaseHas('veiculos', ['id' => $veiculo->id, 'km_atual' => 5150]);
    }

    public function test_cria_registro_km_sem_motorista(): void
    {
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create(['km_atual' => 2000]);

        $this->postJson('/api/km', [
            'veiculo_id' => $veiculo->id,
            'km_atual' => 2100,
        ])->assertCreated();

        $this->assertDatabaseHas('km_registros', [
            'veiculo_id' => $veiculo->id,
            'motorista_id' => null,
            'km_atual' => 2100,
        ]);
    }

    public function test_km_atual_menor_que_km_do_veiculo_retorna_erro(): void
    {
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create(['km_atual' => 5000]);
        $motorista = Motorista::factory()->create();

        $this->postJson('/api/km', [
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'km_atual' => 4000,
        ])->assertUnprocessable();
    }

    public function test_cria_registro_km_sem_veiculo_retorna_erro_de_validacao(): void
    {
        $this->loginGestor();

        $this->postJson('/api/km', [
            'km_atual' => 100,
        ])->assertUnprocessable();
    }
}
