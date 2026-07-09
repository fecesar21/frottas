<?php

namespace Tests\Feature\Veiculo;

use App\Models\Unidade;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Models\ViagemPonto;
use Tests\TestCase;

class VeiculoPosicoesApiTest extends TestCase
{
    public function test_veiculo_com_viagem_em_andamento_e_ponto_gps_aparece_na_resposta(): void
    {
        $this->loginAdmin();

        $veiculo = Veiculo::factory()->create();
        $viagem = Viagem::factory()->create(['veiculo_id' => $veiculo->id, 'status' => 'em_andamento']);
        ViagemPonto::create([
            'viagem_id' => $viagem->id,
            'latitude' => -23.5505,
            'longitude' => -46.6333,
            'capturado_at' => now(),
        ]);

        $response = $this->getJson('/api/veiculos/posicoes')->assertOk();

        $response->assertJsonFragment([
            'veiculo_id' => $veiculo->id,
            'viagem_id' => $viagem->id,
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);
    }

    public function test_veiculo_sem_viagem_ativa_nao_aparece(): void
    {
        $this->loginAdmin();

        Veiculo::factory()->create();

        $response = $this->getJson('/api/veiculos/posicoes')->assertOk();

        $response->assertJsonCount(0);
    }

    public function test_veiculo_com_viagem_ativa_mas_sem_ponto_gps_nao_aparece(): void
    {
        $this->loginAdmin();

        $veiculo = Veiculo::factory()->create();
        Viagem::factory()->create(['veiculo_id' => $veiculo->id, 'status' => 'em_andamento']);

        $response = $this->getJson('/api/veiculos/posicoes')->assertOk();

        $response->assertJsonCount(0);
    }

    public function test_respeita_escopo_de_unidade(): void
    {
        $this->loginAdmin();

        $unidadeA = Unidade::factory()->create();
        $unidadeB = Unidade::factory()->create();

        $veiculoA = Veiculo::factory()->create();
        $veiculoA->unidades()->attach($unidadeA->id);
        $viagemA = Viagem::factory()->create(['veiculo_id' => $veiculoA->id, 'status' => 'em_andamento']);
        ViagemPonto::create([
            'viagem_id' => $viagemA->id,
            'latitude' => -23.5505,
            'longitude' => -46.6333,
            'capturado_at' => now(),
        ]);

        $veiculoB = Veiculo::factory()->create();
        $veiculoB->unidades()->attach($unidadeB->id);
        $viagemB = Viagem::factory()->create(['veiculo_id' => $veiculoB->id, 'status' => 'em_andamento']);
        ViagemPonto::create([
            'viagem_id' => $viagemB->id,
            'latitude' => -22.9,
            'longitude' => -43.2,
            'capturado_at' => now(),
        ]);

        $response = $this->getJson("/api/veiculos/posicoes?unidade_id={$unidadeA->id}")->assertOk();

        $response->assertJsonCount(1);
        $response->assertJsonFragment(['veiculo_id' => $veiculoA->id]);
    }
}
