<?php

namespace Tests\Feature\Localidade;

use App\Models\Localidade;
use Tests\TestCase;

class LocalidadeApiTest extends TestCase
{
    public function test_admin_lista_localidades(): void
    {
        $this->loginAdmin();
        Localidade::factory()->count(2)->create();

        $this->getJson('/api/localidades')->assertOk()->assertJsonCount(2);
    }

    public function test_admin_cria_localidade(): void
    {
        $this->loginAdmin();

        $response = $this->postJson('/api/localidades', [
            'nome' => 'Hospital Parceiro Norte',
            'endereco' => 'Av. Brasil, 500',
            'latitude' => -23.5,
            'longitude' => -46.6,
            'telefone' => '(11) 3333-4444',
            'email' => 'contato@hospitalnorte.com.br',
        ]);

        $response->assertCreated()->assertJsonPath('nome', 'Hospital Parceiro Norte');
        $this->assertDatabaseHas('localidades', ['nome' => 'Hospital Parceiro Norte', 'ativo' => true]);
    }

    public function test_operador_nao_pode_criar_localidade(): void
    {
        $this->loginOperador();

        $this->postJson('/api/localidades', ['nome' => 'Teste'])->assertForbidden();
    }

    public function test_admin_atualiza_localidade(): void
    {
        $this->loginAdmin();
        $localidade = Localidade::factory()->create(['nome' => 'Nome Antigo']);

        $this->patchJson("/api/localidades/{$localidade->id}", ['nome' => 'Nome Novo'])
            ->assertOk()
            ->assertJsonPath('nome', 'Nome Novo');
    }

    public function test_admin_desativa_localidade_sem_apagar(): void
    {
        $this->loginAdmin();
        $localidade = Localidade::factory()->create();

        $this->deleteJson("/api/localidades/{$localidade->id}")->assertOk();

        $this->assertDatabaseHas('localidades', ['id' => $localidade->id, 'ativo' => false]);
    }

    public function test_criacao_exige_nome(): void
    {
        $this->loginAdmin();

        $this->postJson('/api/localidades', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nome']);
    }
}
