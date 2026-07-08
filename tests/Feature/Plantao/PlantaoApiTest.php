<?php

namespace Tests\Feature\Plantao;

use App\Models\ChecklistCategoria;
use App\Models\ChecklistItemModelo;
use App\Models\ChecklistResposta;
use App\Models\Motorista;
use App\Models\PassagemPlantao;
use App\Models\Unidade;
use App\Models\Veiculo;
use Tests\TestCase;

class PlantaoApiTest extends TestCase
{
    public function test_lista_itens_modelo_do_checklist(): void
    {
        $this->loginAdmin();
        $categoria = ChecklistCategoria::create(['nome' => 'Pneus']);
        ChecklistItemModelo::create([
            'categoria_id' => $categoria->id,
            'label' => 'Calibragem',
            'descricao' => 'Verificar calibragem dos pneus',
            'ordem' => 1,
            'ativo' => true,
        ]);

        $this->getJson('/api/plantao/modelo/itens')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_cria_passagem_de_plantao(): void
    {
        $this->loginGestor();

        $unidade = Unidade::factory()->create();
        $veiculo = Veiculo::factory()->create();
        $veiculo->unidades()->attach($unidade->id);
        $saindo = Motorista::factory()->create();
        $saindo->unidades()->attach($unidade->id);
        $entrando = Motorista::factory()->create();
        $entrando->unidades()->attach($unidade->id);

        $response = $this->postJson('/api/plantao', [
            'veiculo_id' => $veiculo->id,
            'motorista_saindo_id' => $saindo->id,
            'motorista_entrando_id' => $entrando->id,
            'turno_saindo' => 'dia',
            'turno_entrando' => 'noite',
            'km_momento' => 5000,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('passagens_plantao', ['veiculo_id' => $veiculo->id]);
    }

    public function test_atualiza_item_do_checklist(): void
    {
        $this->loginGestor();
        $categoria = ChecklistCategoria::create(['nome' => 'Pneus']);
        $item = ChecklistItemModelo::create([
            'categoria_id' => $categoria->id,
            'label' => 'Calibragem',
            'descricao' => 'Verificar calibragem dos pneus',
            'ordem' => 1,
            'ativo' => true,
        ]);
        $plantao = PassagemPlantao::factory()->create();
        $resposta = ChecklistResposta::create([
            'passagem_id' => $plantao->id,
            'item_modelo_id' => $item->id,
            'resultado' => 'pendencia',
        ]);

        $this->patchJson("/api/plantao/{$plantao->id}/item", [
            'item_modelo_id' => $item->id,
            'resultado' => 'ok',
        ])->assertOk();

        $this->assertDatabaseHas('checklist_respostas', ['id' => $resposta->id, 'resultado' => 'ok']);
        $this->assertDatabaseHas('passagens_plantao', ['id' => $plantao->id, 'itens_ok' => 1, 'itens_pendencia' => 0]);
    }

    public function test_finaliza_passagem_de_plantao(): void
    {
        $this->loginGestor();
        $plantao = PassagemPlantao::factory()->create();

        $this->patchJson("/api/plantao/{$plantao->id}/finalizar", ['observacoes_gerais' => 'Tudo ok'])
            ->assertOk()
            ->assertJsonPath('data.observacoes_gerais', 'Tudo ok');

        $this->assertDatabaseHas('passagens_plantao', ['id' => $plantao->id]);
        $this->assertNotNull($plantao->fresh()->finalizado_at);
    }

    public function test_encerra_passagem_de_plantao(): void
    {
        $this->loginGestor();
        $plantao = PassagemPlantao::factory()->create();

        $this->patchJson("/api/plantao/{$plantao->id}/encerrar", [])->assertOk();

        $this->assertNotNull($plantao->fresh()->finalizado_at);
    }
}
