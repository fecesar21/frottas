<?php

namespace Tests\Feature\Veiculo;

use App\Models\Veiculo;
use Tests\TestCase;

class VeiculoApiTest extends TestCase
{
    public function test_lista_veiculos(): void
    {
        $this->loginAdmin();
        Veiculo::factory()->count(3)->create();

        $this->getJson('/api/veiculos')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'placa', 'modelo', 'status']]]);
    }

    public function test_cria_veiculo(): void
    {
        $this->loginGestor();

        $response = $this->postJson('/api/veiculos', [
            'placa'       => 'TST-1234',
            'modelo'      => 'Sprinter',
            'marca'       => 'Mercedes-Benz',
            'ano'         => 2022,
            'combustivel' => 'diesel_s10',
        ]);

        $response->assertCreated()->assertJsonPath('data.placa', 'TST-1234');
        $this->assertDatabaseHas('veiculos', ['placa' => 'TST-1234']);
    }

    public function test_placa_duplicada_retorna_422(): void
    {
        $this->loginGestor();
        Veiculo::factory()->create(['placa' => 'DUP-0001']);

        $this->postJson('/api/veiculos', [
            'placa'  => 'DUP-0001',
            'modelo' => 'Qualquer',
            'ano'    => 2022,
        ])->assertUnprocessable();
    }

    public function test_atualiza_veiculo(): void
    {
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create();

        $this->patchJson("/api/veiculos/{$veiculo->id}", ['cor' => 'Vermelho'])
            ->assertOk()
            ->assertJsonPath('data.cor', 'Vermelho');
    }

    public function test_desativa_veiculo(): void
    {
        $this->loginAdmin();
        $veiculo = Veiculo::factory()->create(['status' => 'disponivel']);

        $this->deleteJson("/api/veiculos/{$veiculo->id}")->assertOk();
        $this->assertDatabaseHas('veiculos', ['id' => $veiculo->id, 'status' => 'inativo']);
    }
}
