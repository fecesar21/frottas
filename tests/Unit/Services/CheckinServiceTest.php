<?php

namespace Tests\Unit\Services;

use App\Models\Checkin;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Services\CheckinService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckinServiceTest extends TestCase
{
    private CheckinService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CheckinService();
    }

    public function test_store_cria_checkin_e_km_registro(): void
    {
        $motorista = Motorista::factory()->create();
        $veiculo   = Veiculo::factory()->create(['km_atual' => 1000, 'status' => 'disponivel']);

        $checkin = $this->service->store([
            'motorista_id' => $motorista->id,
            'veiculo_id'   => $veiculo->id,
            'turno'        => 'dia',
            'km_saida'     => 1000,
        ]);

        $this->assertDatabaseHas('checkins', ['id' => $checkin->id, 'status' => 'ativo']);
        $this->assertDatabaseHas('km_registros', ['veiculo_id' => $veiculo->id, 'km_atual' => 1000]);
        $this->assertDatabaseHas('veiculos', ['id' => $veiculo->id, 'status' => 'em_uso']);
    }

    public function test_store_lanca_excecao_quando_motorista_ja_tem_checkin_ativo(): void
    {
        $motorista = Motorista::factory()->create();
        $veiculo1  = Veiculo::factory()->create(['km_atual' => 1000]);
        $veiculo2  = Veiculo::factory()->create(['km_atual' => 2000]);

        Checkin::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id'   => $veiculo1->id,
            'status'       => 'ativo',
            'km_saida'     => 1000,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->store([
            'motorista_id' => $motorista->id,
            'veiculo_id'   => $veiculo2->id,
            'turno'        => 'dia',
            'km_saida'     => 2000,
        ]);
    }

    public function test_checkout_encerra_checkin_e_libera_veiculo(): void
    {
        $motorista = Motorista::factory()->create();
        $veiculo   = Veiculo::factory()->create(['km_atual' => 1000, 'status' => 'em_uso']);

        $checkin = Checkin::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id'   => $veiculo->id,
            'km_saida'     => 1000,
            'status'       => 'ativo',
        ]);

        $this->service->checkout($checkin, ['km_retorno' => 1500]);

        $this->assertDatabaseHas('checkins', ['id' => $checkin->id, 'status' => 'encerrado', 'km_retorno' => 1500]);
        $this->assertDatabaseHas('veiculos', ['id' => $veiculo->id, 'status' => 'disponivel', 'km_atual' => 1500]);
    }
}
