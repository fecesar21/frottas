<?php

namespace Tests\Feature\Abastecimento;

use App\Models\Abastecimento;
use App\Models\Motorista;
use App\Models\Veiculo;
use Tests\TestCase;

class AbastecimentoApiTest extends TestCase
{
    public function test_remover_abastecimento_e_soft_delete(): void
    {
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create();
        $motorista = Motorista::factory()->create();
        $abastecimento = Abastecimento::create([
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'combustivel' => 'diesel_s10',
            'litros' => 50,
            'valor_litro' => 5.50,
            'km_momento' => 1000,
            'abastecido_at' => now(),
        ]);

        $this->deleteJson("/api/abastecimentos/{$abastecimento->id}")->assertOk();

        $this->assertSoftDeleted('abastecimentos', ['id' => $abastecimento->id]);
    }
}
