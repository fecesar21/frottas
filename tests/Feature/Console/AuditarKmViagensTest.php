<?php

namespace Tests\Feature\Console;

use App\Models\Veiculo;
use App\Models\Viagem;
use Tests\TestCase;

class AuditarKmViagensTest extends TestCase
{
    private function viagem(Veiculo $veiculo, int $saida, int $chegada, string $quando): Viagem
    {
        return Viagem::factory()->create([
            'veiculo_id' => $veiculo->id, 'km_saida' => $saida, 'km_chegada' => $chegada,
            'status' => 'concluida', 'saida_at' => $quando,
        ]);
    }

    public function test_detecta_ida_e_chegada_infladas_juntas_e_marca(): void
    {
        $veiculo = Veiculo::factory()->create();
        $ok1 = $this->viagem($veiculo, 65000, 65100, '2026-10-01 08:00');
        $inflada = $this->viagem($veiculo, 651200, 651260, '2026-10-01 10:00'); // dígito a mais nos dois
        $ok2 = $this->viagem($veiculo, 65300, 65350, '2026-10-01 14:00');

        $this->artisan('frota:auditar-km', ['--marcar' => true])->assertSuccessful();

        $this->assertTrue($inflada->fresh()->km_suspeito);
        $this->assertStringContainsString('acima da viagem anterior', $inflada->fresh()->justificativa_km);
        // A seguinte é sinalizada pela regressão (aponta para a anterior inflada).
        $this->assertTrue($ok2->fresh()->km_suspeito);
        $this->assertFalse($ok1->fresh()->km_suspeito);
    }

    public function test_historico_normal_nao_gera_achados(): void
    {
        $veiculo = Veiculo::factory()->create();
        $this->viagem($veiculo, 1000, 1050, '2026-10-01 08:00');
        $this->viagem($veiculo, 1060, 1500, '2026-10-01 10:00');

        $this->artisan('frota:auditar-km')->expectsOutput('Nenhuma viagem com KM implausível.')->assertSuccessful();
    }
}
