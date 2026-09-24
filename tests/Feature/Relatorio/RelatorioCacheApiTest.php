<?php

namespace Tests\Feature\Relatorio;

use App\Models\Abastecimento;
use App\Models\Motorista;
use App\Models\Veiculo;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RelatorioCacheApiTest extends TestCase
{
    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_dashboard_retorna_resultado_cacheado_apos_novo_abastecimento(): void
    {
        $this->loginAdmin();
        $veiculo = Veiculo::factory()->create();
        $motorista = Motorista::factory()->create();

        $primeira = $this->getJson('/api/relatorios/dashboard')
            ->assertOk()
            ->json();

        // Abastecimento não invalida o cache: se o cache não estivesse
        // ativo, o custo de combustível do mês mudaria na segunda resposta.
        Abastecimento::factory()->create(['veiculo_id' => $veiculo->id, 'motorista_id' => $motorista->id, 'abastecido_at' => now(), 'litros' => 40, 'valor_litro' => 6]);

        $segunda = $this->getJson('/api/relatorios/dashboard')
            ->assertOk()
            ->json();

        $this->assertEquals($primeira, $segunda);
    }

    public function test_colocar_veiculo_em_manutencao_atualiza_dashboard_na_hora(): void
    {
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create(['status' => 'disponivel']);

        $this->getJson('/api/relatorios/dashboard')
            ->assertOk()
            ->assertJsonPath('veiculos.manutencao', 0);

        $this->patchJson("/api/veiculos/{$veiculo->id}", ['status' => 'manutencao'])->assertOk();

        $this->getJson('/api/relatorios/dashboard')
            ->assertOk()
            ->assertJsonPath('veiculos.manutencao', 1)
            ->assertJsonPath('veiculos.disponiveis', 0);
    }

    public function test_eficiencia_retorna_resultado_cacheado_apos_criar_novo_veiculo(): void
    {
        $this->loginAdmin();

        $primeira = $this->getJson('/api/relatorios/eficiencia')
            ->assertOk()
            ->json();

        Veiculo::factory()->create();

        $segunda = $this->getJson('/api/relatorios/eficiencia')
            ->assertOk()
            ->json();

        $this->assertEquals($primeira, $segunda);
    }

    public function test_eficiencia_com_periodos_diferentes_nao_compartilha_cache(): void
    {
        $this->loginAdmin();

        $de1 = now()->startOfMonth()->toDateString();
        $ate1 = now()->toDateString();
        $de2 = now()->subMonths(2)->startOfMonth()->toDateString();
        $ate2 = now()->subMonths(2)->endOfMonth()->toDateString();

        $this->getJson("/api/relatorios/eficiencia?de={$de1}&ate={$ate1}")->assertOk();

        Veiculo::factory()->create();

        // Período diferente não deve colidir com a chave de cache do primeiro período.
        $segunda = $this->getJson("/api/relatorios/eficiencia?de={$de2}&ate={$ate2}")->assertOk();

        $veiculosSegundoPeriodo = count($segunda->json('porVeiculo'));

        $terceira = $this->getJson("/api/relatorios/eficiencia?de={$de2}&ate={$ate2}")->assertOk();

        $this->assertEquals($veiculosSegundoPeriodo, count($terceira->json('porVeiculo')));
        $this->assertEquals($segunda->json(), $terceira->json());
    }
}
