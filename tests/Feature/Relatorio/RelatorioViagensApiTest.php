<?php

namespace Tests\Feature\Relatorio;

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

    public function test_pdf_do_relatorio_de_viagens_e_gerado(): void
    {
        $this->loginAdmin();
        Viagem::factory()->create(['saida_at' => now()]);

        $this->get('/api/relatorios/viagens/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
