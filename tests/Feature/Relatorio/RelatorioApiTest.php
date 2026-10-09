<?php

namespace Tests\Feature\Relatorio;

use App\Models\Abastecimento;
use App\Models\Checkin;
use App\Models\PassagemPlantao;
use App\Models\Veiculo;
use Tests\TestCase;

class RelatorioApiTest extends TestCase
{
    public function test_dashboard_retorna_kpis_gerais(): void
    {
        $this->loginAdmin();
        Veiculo::factory()->count(2)->create(['status' => 'disponivel']);

        $this->getJson('/api/relatorios/dashboard')
            ->assertOk()
            ->assertJsonStructure(['veiculos', 'checkins_ativos', 'km_mes', 'custo_combustivel_mes', 'motoristas', 'cnh_vencendo']);
    }

    public function test_relatorio_abastecimentos_retorna_totais_e_agrupamento_por_veiculo(): void
    {
        $this->loginAdmin();
        $veiculo = Veiculo::factory()->create();
        Abastecimento::factory()->create(['veiculo_id' => $veiculo->id, 'litros' => 50, 'valor_litro' => 5]);

        $this->getJson('/api/relatorios/abastecimentos')
            ->assertOk()
            ->assertJsonStructure(['rows', 'totais', 'por_veiculo']);
    }

    public function test_relatorio_abastecimentos_filtra_por_veiculo(): void
    {
        $this->loginAdmin();
        $alvo = Veiculo::factory()->create();
        $outro = Veiculo::factory()->create();
        Abastecimento::factory()->create(['veiculo_id' => $alvo->id, 'litros' => 40, 'valor_litro' => 5, 'abastecido_at' => now()]);
        Abastecimento::factory()->create(['veiculo_id' => $outro->id, 'litros' => 10, 'valor_litro' => 5, 'abastecido_at' => now()]);

        $resposta = $this->getJson('/api/relatorios/abastecimentos?veiculo_id='.$alvo->id)->assertOk();

        $this->assertCount(1, $resposta->json('rows'));
        $this->assertSame($alvo->placa, $resposta->json('rows.0.placa'));
        $this->assertEquals(40, $resposta->json('totais.total_litros'));
    }

    public function test_relatorio_plantao_retorna_totais(): void
    {
        $this->loginAdmin();
        PassagemPlantao::factory()->create();

        $this->getJson('/api/relatorios/plantao')
            ->assertOk()
            ->assertJsonStructure(['rows', 'totais']);
    }

    public function test_relatorio_checkins_retorna_ultimos_registros(): void
    {
        $this->loginAdmin();
        Checkin::factory()->create();

        $this->getJson('/api/relatorios/checkins')->assertOk();
    }
}
