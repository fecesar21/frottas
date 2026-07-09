<?php

namespace Tests\Feature\Relatorio;

use App\Models\Abastecimento;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Models\Viagem;
use Tests\TestCase;

class RelatorioEficienciaApiTest extends TestCase
{
    public function test_veiculo_com_abastecimentos_e_viagens_retorna_custo_por_km_e_consumo_corretos(): void
    {
        $this->loginAdmin();

        $veiculo = Veiculo::factory()->create();

        // 100 litros a R$ 5,00/litro = R$ 500,00
        Abastecimento::factory()->create([
            'veiculo_id' => $veiculo->id,
            'litros' => 100,
            'valor_litro' => 5,
            'abastecido_at' => now(),
        ]);

        // 500 km percorridos
        Viagem::factory()->concluida()->create([
            'veiculo_id' => $veiculo->id,
            'km_saida' => 1000,
            'km_chegada' => 1500,
            'saida_at' => now(),
        ]);

        $response = $this->getJson('/api/relatorios/eficiencia')
            ->assertOk()
            ->assertJsonStructure(['porVeiculo', 'rankingMotoristas']);

        $linha = collect($response->json('porVeiculo'))
            ->firstWhere('veiculo_id', $veiculo->id);

        $this->assertNotNull($linha);
        $this->assertEquals(500, $linha['km_total']);
        $this->assertEquals(100, $linha['total_litros']);
        $this->assertEquals(500, $linha['custo_total']);
        $this->assertEquals(1.0, $linha['custo_por_km']);
        $this->assertEquals(5.0, $linha['consumo_km_por_litro']);
    }

    public function test_veiculo_com_dois_abastecimentos_de_litros_identicos_nao_subconta_o_total(): void
    {
        // Prova de que a agregação não depende de SUM(DISTINCT litros),
        // que subcontaria quando dois abastecimentos têm exatamente o
        // mesmo valor de litros (ex.: dois tanques cheios de 40.000L).
        $this->loginAdmin();

        $veiculo = Veiculo::factory()->create();

        Abastecimento::factory()->create([
            'veiculo_id' => $veiculo->id,
            'litros' => 40,
            'valor_litro' => 5,
            'abastecido_at' => now(),
        ]);

        Abastecimento::factory()->create([
            'veiculo_id' => $veiculo->id,
            'litros' => 40,
            'valor_litro' => 5,
            'abastecido_at' => now(),
        ]);

        $response = $this->getJson('/api/relatorios/eficiencia')->assertOk();

        $linha = collect($response->json('porVeiculo'))
            ->firstWhere('veiculo_id', $veiculo->id);

        $this->assertNotNull($linha);
        $this->assertEquals(80, $linha['total_litros']);
        $this->assertEquals(400, $linha['custo_total']);
    }

    public function test_veiculo_sem_viagens_no_periodo_retorna_null_em_vez_de_erro_de_divisao(): void
    {
        $this->loginAdmin();

        $veiculo = Veiculo::factory()->create();
        Abastecimento::factory()->create([
            'veiculo_id' => $veiculo->id,
            'litros' => 50,
            'valor_litro' => 5,
            'abastecido_at' => now(),
        ]);

        $response = $this->getJson('/api/relatorios/eficiencia')->assertOk();

        $linha = collect($response->json('porVeiculo'))
            ->firstWhere('veiculo_id', $veiculo->id);

        $this->assertNotNull($linha);
        $this->assertEquals(0, $linha['km_total']);
        $this->assertNull($linha['custo_por_km']);
        // total_litros > 0, mas km_total = 0 -> consumo é 0.0, não null
        $this->assertEquals(0.0, $linha['consumo_km_por_litro']);
    }

    public function test_veiculo_sem_abastecimentos_no_periodo_retorna_null_no_consumo(): void
    {
        $this->loginAdmin();

        $veiculo = Veiculo::factory()->create();
        Viagem::factory()->concluida()->create([
            'veiculo_id' => $veiculo->id,
            'km_saida' => 1000,
            'km_chegada' => 1200,
            'saida_at' => now(),
        ]);

        $response = $this->getJson('/api/relatorios/eficiencia')->assertOk();

        $linha = collect($response->json('porVeiculo'))
            ->firstWhere('veiculo_id', $veiculo->id);

        $this->assertNotNull($linha);
        $this->assertEquals(0, $linha['total_litros']);
        $this->assertNull($linha['consumo_km_por_litro']);
        // custo_total é 0, km_total é 200 -> custo_por_km = 0.0, não null
        $this->assertEquals(0.0, $linha['custo_por_km']);
    }

    public function test_ranking_de_motoristas_ordenado_por_km_decrescente(): void
    {
        $this->loginAdmin();

        $motoristaTop = Motorista::factory()->create();
        $motoristaBaixo = Motorista::factory()->create();

        Viagem::factory()->concluida()->create([
            'motorista_id' => $motoristaTop->id,
            'km_saida' => 0,
            'km_chegada' => 1000,
            'saida_at' => now(),
        ]);

        Viagem::factory()->concluida()->create([
            'motorista_id' => $motoristaBaixo->id,
            'km_saida' => 0,
            'km_chegada' => 100,
            'saida_at' => now(),
        ]);

        $response = $this->getJson('/api/relatorios/eficiencia')->assertOk();

        $ranking = collect($response->json('rankingMotoristas'))->pluck('km_total', 'motorista_id');

        $this->assertEquals(1000, $ranking[$motoristaTop->id]);
        $this->assertEquals(100, $ranking[$motoristaBaixo->id]);

        $ids = collect($response->json('rankingMotoristas'))->pluck('motorista_id')->values();
        $this->assertEquals(
            $motoristaTop->id,
            $ids->first(fn ($id) => in_array($id, [$motoristaTop->id, $motoristaBaixo->id]))
        );
    }
}
