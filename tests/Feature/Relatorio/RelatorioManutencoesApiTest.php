<?php

namespace Tests\Feature\Relatorio;

use App\Models\Veiculo;
use App\Models\VeiculoManutencao;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RelatorioManutencoesApiTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_entrar_e_sair_de_manutencao_registra_historico_com_motivo(): void
    {
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create(['status' => 'disponivel']);

        Carbon::setTestNow('2026-09-10 08:00:00');
        $this->patchJson("/api/veiculos/{$veiculo->id}", ['status' => 'manutencao', 'manutencao_motivo' => 'Troca de óleo'])
            ->assertOk();

        $registro = VeiculoManutencao::sole();
        $this->assertSame('Troca de óleo', $registro->motivo);
        $this->assertNull($registro->fim);

        Carbon::setTestNow('2026-09-10 11:30:00');
        $this->patchJson("/api/veiculos/{$veiculo->id}", ['status' => 'disponivel'])->assertOk();

        $registro->refresh();
        $this->assertSame('2026-09-10 11:30:00', $registro->fim->toDateTimeString());
        $this->assertNull($veiculo->fresh()->manutencao_inicio);
    }

    public function test_repetir_status_manutencao_nao_duplica_registro(): void
    {
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create(['status' => 'disponivel']);

        $this->patchJson("/api/veiculos/{$veiculo->id}", ['status' => 'manutencao'])->assertOk();
        $this->patchJson("/api/veiculos/{$veiculo->id}", ['status' => 'manutencao'])->assertOk();

        $this->assertSame(1, VeiculoManutencao::count());
    }

    public function test_desativar_veiculo_em_manutencao_fecha_o_registro(): void
    {
        $this->loginAdmin();
        $veiculo = Veiculo::factory()->create(['status' => 'disponivel']);
        $this->patchJson("/api/veiculos/{$veiculo->id}", ['status' => 'manutencao'])->assertOk();

        $this->deleteJson("/api/veiculos/{$veiculo->id}")->assertOk();

        $this->assertNotNull(VeiculoManutencao::sole()->fim);
    }

    public function test_relatorio_recorta_o_tempo_ao_periodo_e_agrega_por_veiculo(): void
    {
        Carbon::setTestNow('2026-09-20 12:00:00');
        $this->loginGestor();
        $a = Veiculo::factory()->create(['placa' => 'AAA1A11']);
        $b = Veiculo::factory()->create(['placa' => 'BBB2B22']);

        // Começou em agosto e terminou em 02/09 10:00 → no período conta só 01/09 00:00 a 02/09 10:00 (34 h = 2040 min).
        VeiculoManutencao::factory()->create(['veiculo_id' => $a->id, 'inicio' => '2026-08-30 10:00:00', 'fim' => '2026-09-02 10:00:00']);
        // Dentro do período: 5 horas.
        VeiculoManutencao::factory()->create(['veiculo_id' => $a->id, 'inicio' => '2026-09-05 08:00:00', 'fim' => '2026-09-05 13:00:00']);
        // Aberta: conta até agora (20/09 12:00) → 2 horas.
        VeiculoManutencao::factory()->create(['veiculo_id' => $b->id, 'inicio' => '2026-09-20 10:00:00', 'fim' => null, 'motivo' => 'Pneus']);
        // Fora do período: ignorada.
        VeiculoManutencao::factory()->create(['veiculo_id' => $b->id, 'inicio' => '2026-07-01 08:00:00', 'fim' => '2026-07-02 08:00:00']);

        $json = $this->getJson('/api/relatorios/manutencoes?de=2026-09-01&ate=2026-09-30')
            ->assertOk()
            ->json();

        $this->assertSame(3, $json['totais']['total_manutencoes']);
        $this->assertSame(2040 + 300 + 120, $json['totais']['tempo_total_min']);
        $this->assertSame(820, $json['totais']['tempo_medio_min']);
        $this->assertSame(1, $json['totais']['em_manutencao_agora']);

        $this->assertSame('AAA1A11', $json['por_veiculo'][0]['placa']);
        $this->assertSame(2340, $json['por_veiculo'][0]['tempo_total_min']);
        $this->assertSame(2, $json['por_veiculo'][0]['manutencoes']);

        $this->assertCount(1, $json['em_andamento']);
        $this->assertSame('Pneus', $json['em_andamento'][0]['motivo']);
        $this->assertSame(120, $json['em_andamento'][0]['duracao_min']);

        $longa = collect($json['rows'])->firstWhere('inicio', '2026-08-30 10:00:00');
        $this->assertSame(3 * 24 * 60, $longa['duracao_min']);
        $this->assertSame(2040, $longa['duracao_periodo_min']);
    }

    public function test_pdf_de_manutencoes_e_gerado(): void
    {
        $this->loginAdmin();
        VeiculoManutencao::factory()->create();

        $this->get('/api/relatorios/manutencoes/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_relatorio_agrega_por_tipo_e_informa_responsavel(): void
    {
        Carbon::setTestNow('2026-09-20 12:00:00');
        $gestor = $this->loginGestor();
        $veiculo = Veiculo::factory()->create();
        VeiculoManutencao::factory()->create([
            'veiculo_id' => $veiculo->id, 'tipo' => 'troca_oleo', 'aberta_por_id' => $gestor->id,
            'inicio' => '2026-09-10 08:00:00', 'fim' => '2026-09-10 10:00:00',
        ]);
        VeiculoManutencao::factory()->create([
            'veiculo_id' => $veiculo->id, 'tipo' => null,
            'inicio' => '2026-09-11 08:00:00', 'fim' => '2026-09-11 09:00:00',
        ]);

        $json = $this->getJson('/api/relatorios/manutencoes?de=2026-09-01&ate=2026-09-30')->assertOk()->json();

        $this->assertSame('Troca de óleo do motor', $json['por_tipo'][0]['tipo_label']);
        $this->assertSame(120, $json['por_tipo'][0]['tempo_total_min']);
        $this->assertSame('Não informado', $json['por_tipo'][1]['tipo_label']);
        $linha = collect($json['rows'])->firstWhere('tipo', 'troca_oleo');
        $this->assertSame($gestor->nome, $linha['aberta_por']);
    }
}
