<?php

namespace Tests\Feature\Unidade;

use App\Models\Motorista;
use App\Models\Unidade;
use App\Models\Veiculo;
use Tests\TestCase;

class UnidadeApiTest extends TestCase
{
    public function test_lista_unidades(): void
    {
        $this->loginAdmin();
        Unidade::factory()->count(2)->create();

        $this->getJson('/api/unidades')->assertOk()->assertJsonCount(2);
    }

    public function test_admin_cria_unidade(): void
    {
        $this->loginAdmin();

        $this->postJson('/api/unidades', ['nome' => 'Filial Sul', 'tipo' => 'filial'])
            ->assertCreated()
            ->assertJsonPath('nome', 'Filial Sul');
    }

    public function test_gestor_nao_pode_criar_unidade(): void
    {
        $this->loginGestor();

        $this->postJson('/api/unidades', ['nome' => 'Filial Sul', 'tipo' => 'filial'])
            ->assertForbidden();
    }

    public function test_admin_desativa_unidade(): void
    {
        $this->loginAdmin();
        $unidade = Unidade::factory()->create(['ativo' => true]);

        $this->deleteJson("/api/unidades/{$unidade->id}")->assertOk();
        $this->assertDatabaseHas('unidades', ['id' => $unidade->id, 'ativo' => false]);
    }

    public function test_vincula_e_desvincula_motorista(): void
    {
        $this->loginAdmin();
        $unidade = Unidade::factory()->create();
        $motorista = Motorista::factory()->create();

        $this->postJson("/api/unidades/{$unidade->id}/motoristas", ['motorista_ids' => [$motorista->id]])
            ->assertOk();
        $this->assertDatabaseHas('motorista_unidade', ['unidade_id' => $unidade->id, 'motorista_id' => $motorista->id]);

        $this->deleteJson("/api/unidades/{$unidade->id}/motoristas/{$motorista->id}")->assertOk();
        $this->assertDatabaseMissing('motorista_unidade', ['unidade_id' => $unidade->id, 'motorista_id' => $motorista->id]);
    }

    public function test_vincula_e_desvincula_veiculo(): void
    {
        $this->loginAdmin();
        $unidade = Unidade::factory()->create();
        $veiculo = Veiculo::factory()->create();

        $this->postJson("/api/unidades/{$unidade->id}/veiculos", ['veiculo_ids' => [$veiculo->id]])
            ->assertOk();
        $this->assertDatabaseHas('veiculo_unidade', ['unidade_id' => $unidade->id, 'veiculo_id' => $veiculo->id]);

        $this->deleteJson("/api/unidades/{$unidade->id}/veiculos/{$veiculo->id}")->assertOk();
        $this->assertDatabaseMissing('veiculo_unidade', ['unidade_id' => $unidade->id, 'veiculo_id' => $veiculo->id]);
    }
}
