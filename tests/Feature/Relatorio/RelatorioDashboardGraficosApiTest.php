<?php

namespace Tests\Feature\Relatorio;

use App\Models\Abastecimento;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Models\Viagem;
use Carbon\Carbon;
use Tests\TestCase;

class RelatorioDashboardGraficosApiTest extends TestCase
{
    public function test_endpoint_retorna_estrutura_esperada(): void
    {
        $this->loginAdmin();

        $this->getJson('/api/relatorios/dashboard/graficos')
            ->assertOk()
            ->assertJsonStructure([
                'abastecimento_mensal',
                'km_mensal',
                'viagens_por_motivo',
                'km_por_motorista',
                'viagens_por_motorista',
                'tempo_medio_motorista',
            ]);
    }

    public function test_serie_abastecimento_mensal_tem_12_meses_e_soma_correta_dentro_da_janela(): void
    {
        $this->loginAdmin();

        $veiculo = Veiculo::factory()->create();
        $mesFiltro = Carbon::create(2026, 6, 15);

        // 100 litros a R$ 5,00 = R$ 500,00 no mês filtrado
        Abastecimento::factory()->create([
            'veiculo_id' => $veiculo->id,
            'combustivel' => 'diesel_s10',
            'litros' => 100,
            'valor_litro' => 5,
            'abastecido_at' => $mesFiltro->copy()->startOfMonth()->addDays(2),
        ]);

        // Fora da janela de 12 meses (13 meses antes) — não deve aparecer
        Abastecimento::factory()->create([
            'veiculo_id' => $veiculo->id,
            'combustivel' => 'diesel_s10',
            'litros' => 999,
            'valor_litro' => 5,
            'abastecido_at' => $mesFiltro->copy()->subMonths(13),
        ]);

        $response = $this->getJson('/api/relatorios/dashboard/graficos?mes=6&ano=2026')->assertOk();

        $serie = collect($response->json('abastecimento_mensal'));
        $this->assertCount(12, $serie);

        $bucketMes = $serie->firstWhere('mes', '2026-06');
        $this->assertNotNull($bucketMes);
        $this->assertEquals(500, $bucketMes['total']);

        $somaTotal = $serie->sum('total');
        $this->assertEquals(500, $somaTotal);
    }

    public function test_serie_km_mensal_soma_km_das_viagens_com_chegada(): void
    {
        $this->loginAdmin();

        $veiculo = Veiculo::factory()->create();
        $mesFiltro = Carbon::create(2026, 6, 15);

        Viagem::factory()->concluida()->create([
            'veiculo_id' => $veiculo->id,
            'km_saida' => 1000,
            'km_chegada' => 1300,
            'saida_at' => $mesFiltro->copy()->startOfMonth()->addDays(3),
        ]);

        // Viagem sem km_chegada (em andamento) não deve contar
        Viagem::factory()->create([
            'veiculo_id' => $veiculo->id,
            'km_saida' => 2000,
            'km_chegada' => null,
            'saida_at' => $mesFiltro->copy()->startOfMonth()->addDays(4),
            'status' => 'em_andamento',
        ]);

        $response = $this->getJson('/api/relatorios/dashboard/graficos?mes=6&ano=2026')->assertOk();

        $bucketMes = collect($response->json('km_mensal'))->firstWhere('mes', '2026-06');
        $this->assertEquals(300, $bucketMes['total']);
    }

    public function test_viagens_por_motivo_agrupa_e_calcula_percentual(): void
    {
        $this->loginAdmin();
        $mesFiltro = Carbon::create(2026, 6, 15);

        Viagem::factory()->count(2)->create([
            'motivo_viagem' => 'Consulta',
            'saida_at' => $mesFiltro->copy()->startOfMonth()->addDays(1),
        ]);

        Viagem::factory()->create([
            'motivo_viagem' => 'Exame',
            'saida_at' => $mesFiltro->copy()->startOfMonth()->addDays(2),
        ]);

        $response = $this->getJson('/api/relatorios/dashboard/graficos?mes=6&ano=2026')->assertOk();

        $porMotivo = collect($response->json('viagens_por_motivo'))->keyBy('motivo');

        $this->assertEquals(2, $porMotivo['Consulta']['total']);
        $this->assertEquals(66.7, $porMotivo['Consulta']['percentual']);
        $this->assertEquals(1, $porMotivo['Exame']['total']);
        $this->assertEquals(33.3, $porMotivo['Exame']['percentual']);
    }

    public function test_viagem_sem_motivo_agrupa_como_nao_informado(): void
    {
        $this->loginAdmin();
        $mesFiltro = Carbon::create(2026, 6, 15);

        Viagem::factory()->create([
            'motivo_viagem' => null,
            'saida_at' => $mesFiltro->copy()->startOfMonth()->addDays(1),
        ]);

        $response = $this->getJson('/api/relatorios/dashboard/graficos?mes=6&ano=2026')->assertOk();

        $porMotivo = collect($response->json('viagens_por_motivo'))->keyBy('motivo');

        $this->assertEquals(1, $porMotivo['Não informado']['total']);
    }

    public function test_km_por_motorista_soma_corretamente_no_mes_filtrado(): void
    {
        $this->loginAdmin();

        $motorista = Motorista::factory()->create();
        $mesFiltro = Carbon::create(2026, 6, 15);

        Viagem::factory()->concluida()->create([
            'motorista_id' => $motorista->id,
            'km_saida' => 0,
            'km_chegada' => 400,
            'saida_at' => $mesFiltro->copy()->startOfMonth()->addDays(1),
        ]);

        $response = $this->getJson('/api/relatorios/dashboard/graficos?mes=6&ano=2026')->assertOk();

        $linha = collect($response->json('km_por_motorista'))->firstWhere('nome', $motorista->nome);
        $this->assertNotNull($linha);
        $this->assertEquals(400, $linha['km_total']);
        $this->assertEquals(100.0, $linha['percentual']);
    }

    public function test_tempo_medio_por_motorista_calculado_via_carbon(): void
    {
        $this->loginAdmin();

        $motorista = Motorista::factory()->create();
        $mesFiltro = Carbon::create(2026, 6, 15);
        $saida = $mesFiltro->copy()->startOfMonth()->addDays(1)->setTime(8, 0);

        Viagem::factory()->create([
            'motorista_id' => $motorista->id,
            'status' => 'concluida',
            'saida_at' => $saida,
            'chegada_at' => $saida->copy()->addMinutes(90),
            'km_saida' => 0,
            'km_chegada' => 50,
        ]);

        $response = $this->getJson('/api/relatorios/dashboard/graficos?mes=6&ano=2026')->assertOk();

        $linha = collect($response->json('tempo_medio_motorista'))->firstWhere('nome', $motorista->nome);
        $this->assertNotNull($linha);
        $this->assertEquals(90, $linha['tempo_medio_minutos']);
    }

    public function test_filtro_mes_ano_isola_dados_de_outros_meses(): void
    {
        $this->loginAdmin();

        $motorista = Motorista::factory()->create();

        // Viagem em julho, fora do mês filtrado (junho)
        Viagem::factory()->concluida()->create([
            'motorista_id' => $motorista->id,
            'motivo_viagem' => 'Consulta',
            'km_saida' => 0,
            'km_chegada' => 100,
            'saida_at' => Carbon::create(2026, 7, 10),
        ]);

        $response = $this->getJson('/api/relatorios/dashboard/graficos?mes=6&ano=2026')->assertOk();

        $this->assertEmpty($response->json('viagens_por_motivo'));
        $this->assertEmpty($response->json('km_por_motorista'));
        $this->assertEmpty($response->json('viagens_por_motorista'));
        $this->assertEmpty($response->json('tempo_medio_motorista'));
    }
}
