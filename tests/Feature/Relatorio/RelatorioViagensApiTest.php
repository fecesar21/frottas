<?php

namespace Tests\Feature\Relatorio;

use App\Models\Colaborador;
use App\Models\Viagem;
use Tests\TestCase;

class RelatorioViagensApiTest extends TestCase
{
    public function test_gestor_visualiza_viagens_com_km_e_duracao_calculados(): void
    {
        $this->loginGestor();
        $saida = now()->startOfDay()->addHours(8);
        $concluida = Viagem::factory()->create([
            'saida_at' => $saida,
            'chegada_at' => $saida->copy()->addMinutes(95),
            'km_saida' => 1000,
            'km_chegada' => 1120,
            'status' => 'concluida',
        ]);
        $emAndamento = Viagem::factory()->create([
            'saida_at' => $saida,
            'km_saida' => 500,
            'km_chegada' => null,
            'chegada_at' => null,
        ]);

        $resposta = $this->getJson('/api/relatorios/viagens')
            ->assertOk()
            ->assertJsonStructure(['rows', 'totais', 'por_motorista', 'de', 'ate']);

        $linhas = collect($resposta->json('rows'))->keyBy('id');
        $this->assertSame(120, (int) $linhas[$concluida->id]['km_percorrido']);
        $this->assertSame(95, $linhas[$concluida->id]['duracao_min']);
        $this->assertNull($linhas[$emAndamento->id]['km_percorrido']);
        $this->assertNull($linhas[$emAndamento->id]['duracao_min']);

        $this->assertSame(2, $resposta->json('totais.total_viagens'));
        $this->assertSame(120, (int) $resposta->json('totais.km_total'));
        $this->assertEquals(95, $resposta->json('totais.duracao_media_min'));
    }

    public function test_relatorio_lista_colaboradores_transportados(): void
    {
        $this->loginGestor();
        $comColaboradores = Viagem::factory()->create(['saida_at' => now(), 'motivo_viagem' => 'transporte_colaborador']);
        $comColaboradores->colaboradores()->sync([
            Colaborador::factory()->create(['nome' => 'BRUNO LIMA'])->id,
            Colaborador::factory()->create(['nome' => 'ANA PAULA'])->id,
        ]);
        $semColaboradores = Viagem::factory()->create(['saida_at' => now()]);

        $linhas = collect($this->getJson('/api/relatorios/viagens')->assertOk()->json('rows'))->keyBy('id');

        $this->assertSame('ANA PAULA, BRUNO LIMA', $linhas[$comColaboradores->id]['colaboradores']);
        $this->assertNull($linhas[$semColaboradores->id]['colaboradores']);
    }

    public function test_pdf_do_relatorio_de_viagens_e_gerado(): void
    {
        $this->loginAdmin();
        Viagem::factory()->create(['saida_at' => now()]);

        $this->get('/api/relatorios/viagens/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_relatorio_viagens_filtra_por_motorista_e_motivo(): void
    {
        $this->loginGestor();
        $alvo = Viagem::factory()->create(['saida_at' => now(), 'motivo_viagem' => 'transferencia_paciente']);
        $mesmoMotoristaOutroMotivo = Viagem::factory()->create([
            'saida_at' => now(), 'motorista_id' => $alvo->motorista_id, 'motivo_viagem' => 'transporte_colaborador',
        ]);
        Viagem::factory()->create(['saida_at' => now(), 'motivo_viagem' => 'transferencia_paciente']);

        $porMotorista = $this->getJson('/api/relatorios/viagens?motorista_id='.$alvo->motorista_id)->assertOk();
        $this->assertEqualsCanonicalizing(
            [$alvo->id, $mesmoMotoristaOutroMotivo->id],
            collect($porMotorista->json('rows'))->pluck('id')->all()
        );

        $ambos = $this->getJson('/api/relatorios/viagens?motorista_id='.$alvo->motorista_id.'&motivo=transferencia_paciente')->assertOk();
        $this->assertSame([$alvo->id], collect($ambos->json('rows'))->pluck('id')->all());
        $this->assertSame(1, $ambos->json('totais.total_viagens'));
    }

    public function test_relatorio_viagens_filtra_por_varios_motoristas(): void
    {
        $this->loginGestor();
        $a = Viagem::factory()->create(['saida_at' => now()]);
        $b = Viagem::factory()->create(['saida_at' => now()]);
        Viagem::factory()->create(['saida_at' => now()]);

        $resposta = $this->getJson('/api/relatorios/viagens?'.http_build_query(['motorista_ids' => [$a->motorista_id, $b->motorista_id]]))
            ->assertOk();

        $this->assertEqualsCanonicalizing([$a->id, $b->id], collect($resposta->json('rows'))->pluck('id')->all());
    }
}
