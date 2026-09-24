<?php

namespace Tests\Feature\Relatorio;

use App\Models\Abastecimento;
use App\Models\Motorista;
use App\Models\Viagem;
use Tests\TestCase;

class RelatorioMotoristasApiTest extends TestCase
{
    public function test_gestor_visualiza_motoristas_ativos_no_relatorio(): void
    {
        $this->loginGestor();
        $ativo = Motorista::factory()->create(['nome' => 'Ana Ativa']);
        Motorista::factory()->create(['nome' => 'Ivo Inativo', 'status' => 'inativo']);

        $this->getJson('/api/relatorios/motoristas')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $ativo->id)
            ->assertJsonStructure([['id', 'nome', 'total_viagens', 'km_total', 'total_abastecimentos', 'total_plantoes', 'cnh_status']]);
    }

    public function test_km_total_nao_e_inflado_por_multiplos_abastecimentos(): void
    {
        $this->loginAdmin();
        $motorista = Motorista::factory()->create();
        Viagem::factory()->create(['motorista_id' => $motorista->id, 'km_saida' => 1000, 'km_chegada' => 1100, 'status' => 'concluida']);
        Viagem::factory()->create(['motorista_id' => $motorista->id, 'km_saida' => 2000, 'km_chegada' => 2050, 'status' => 'concluida']);
        Viagem::factory()->create(['motorista_id' => $motorista->id, 'km_saida' => 3000, 'km_chegada' => null]);
        Abastecimento::factory()->count(3)->create(['motorista_id' => $motorista->id]);

        $linha = collect($this->getJson('/api/relatorios/motoristas')->assertOk()->json())
            ->firstWhere('id', $motorista->id);

        $this->assertSame(3, (int) $linha['total_viagens']);
        $this->assertSame(150, (int) $linha['km_total']);
        $this->assertSame(3, (int) $linha['total_abastecimentos']);
    }

    public function test_status_da_cnh_e_calculado_pela_validade(): void
    {
        $this->loginAdmin();
        $vencida = Motorista::factory()->create(['cnh_validade' => now()->subDay()->toDateString()]);
        $vencendo = Motorista::factory()->create(['cnh_validade' => now()->addDays(10)->toDateString()]);
        $ok = Motorista::factory()->create(['cnh_validade' => now()->addDays(90)->toDateString()]);

        $status = collect($this->getJson('/api/relatorios/motoristas')->assertOk()->json())
            ->pluck('cnh_status', 'id');

        $this->assertSame('vencida', $status[$vencida->id]);
        $this->assertSame('vencendo', $status[$vencendo->id]);
        $this->assertSame('ok', $status[$ok->id]);
    }

    public function test_pdf_do_relatorio_de_motoristas_e_gerado(): void
    {
        $this->loginAdmin();
        Motorista::factory()->create();

        $this->get('/api/relatorios/motoristas/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
