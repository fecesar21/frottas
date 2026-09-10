<?php

namespace Tests\Feature\Localidade;

use App\Models\Localidade;
use App\Models\Unidade;
use Tests\TestCase;

class PontoViagemApiTest extends TestCase
{
    public function test_lista_unidades_e_localidades_ativas_combinadas(): void
    {
        $this->loginOperador();

        Unidade::factory()->create(['nome' => 'Hospital Central', 'ativo' => true]);
        Unidade::factory()->create(['nome' => 'Filial Desativada', 'ativo' => false]);
        Localidade::factory()->create(['nome' => 'Clínica Parceira', 'ativo' => true]);
        Localidade::factory()->create(['nome' => 'Local Inativo', 'ativo' => false]);

        $response = $this->getJson('/api/pontos-viagem')->assertOk();
        $nomes = collect($response->json())->pluck('nome')->all();

        $this->assertEqualsCanonicalizing(['Hospital Central', 'Clínica Parceira'], $nomes);
    }

    public function test_cada_item_indica_o_tipo_de_origem(): void
    {
        $this->loginOperador();

        $unidade = Unidade::factory()->create(['nome' => 'Hospital A']);
        $localidade = Localidade::factory()->create(['nome' => 'Local B']);

        $response = $this->getJson('/api/pontos-viagem')->assertOk();
        $itens = collect($response->json())->keyBy('nome');

        $this->assertSame('unidade', $itens['Hospital A']['tipo']);
        $this->assertSame($unidade->id, $itens['Hospital A']['id']);
        $this->assertSame('localidade', $itens['Local B']['tipo']);
        $this->assertSame($localidade->id, $itens['Local B']['id']);
    }

    public function test_solicitante_acessa_pontos_viagem(): void
    {
        // Regressão: a rota precisa estar nomeada ('pontos-viagem.index') e
        // constar na allowlist de App\Http\Middleware\RestringirSolicitante,
        // senão usuários com perfil "solicitante" (público-alvo da tela de
        // Nova Solicitação) recebem 403 e o formulário fica sem opções de
        // Origem/Destino.
        $this->loginAs('solicitante');

        Unidade::factory()->create(['nome' => 'Hospital Central', 'ativo' => true]);
        Localidade::factory()->create(['nome' => 'Clínica Parceira', 'ativo' => true]);

        $this->getJson('/api/pontos-viagem')->assertOk();
    }
}
