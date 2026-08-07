<?php

namespace Tests\Unit\Models;

use App\Models\Motorista;
use Tests\TestCase;

class MotoristaTest extends TestCase
{
    public function test_dias_para_vencer_cnh_positivo(): void
    {
        $motorista = Motorista::factory()->make([
            'cnh_validade' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $this->assertGreaterThan(0, $motorista->diasParaVencerCnh);
    }

    public function test_dias_para_vencer_cnh_negativo_quando_vencida(): void
    {
        $motorista = Motorista::factory()->make([
            'cnh_validade' => now()->subDays(10)->format('Y-m-d'),
        ]);

        $this->assertLessThan(0, $motorista->diasParaVencerCnh);
    }

    public function test_motorista_tem_relacoes_definidas(): void
    {
        $motorista = new Motorista();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $motorista->checkins());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $motorista->viagens());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $motorista->abastecimentos());
    }
}
