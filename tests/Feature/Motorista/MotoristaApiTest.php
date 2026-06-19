<?php

namespace Tests\Feature\Motorista;

use App\Models\Motorista;
use Tests\TestCase;

class MotoristaApiTest extends TestCase
{
    public function test_lista_motoristas(): void
    {
        $this->loginAdmin();
        Motorista::factory()->count(3)->create();

        $this->getJson('/api/motoristas')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'nome', 'cnh_categoria', 'status']]]);
    }

    public function test_cria_motorista(): void
    {
        $this->loginGestor();

        $this->postJson('/api/motoristas', [
            'nome'          => 'João Silva',
            'cpf'           => '111.111.111-11',
            'cnh_numero'    => '12345678901',
            'cnh_categoria' => 'D',
            'cnh_validade'  => '2028-01-01',
        ])->assertCreated()->assertJsonPath('data.nome', 'João Silva');
    }

    public function test_cpf_duplicado_retorna_422(): void
    {
        $this->loginGestor();
        Motorista::factory()->create(['cpf' => '999.999.999-99']);

        $this->postJson('/api/motoristas', [
            'nome'          => 'Outro',
            'cpf'           => '999.999.999-99',
            'cnh_numero'    => '99999999999',
            'cnh_categoria' => 'B',
            'cnh_validade'  => '2027-01-01',
        ])->assertUnprocessable();
    }

    public function test_alerta_cnh(): void
    {
        $this->loginAdmin();

        $this->getJson('/api/motoristas/alertas/cnh')->assertOk();
    }

    public function test_desativa_motorista(): void
    {
        $this->loginAdmin();
        $motorista = Motorista::factory()->create(['status' => 'ativo']);

        $this->deleteJson("/api/motoristas/{$motorista->id}")->assertOk();
        $this->assertDatabaseHas('motoristas', ['id' => $motorista->id, 'status' => 'inativo']);
    }
}
