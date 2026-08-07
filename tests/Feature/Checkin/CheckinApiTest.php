<?php

namespace Tests\Feature\Checkin;

use App\Models\Checkin;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Models\Viagem;
use Tests\TestCase;

class CheckinApiTest extends TestCase
{
    public function test_cria_checkin_com_sucesso(): void
    {
        $this->loginAdmin();
        $motorista = Motorista::factory()->create();
        $veiculo = Veiculo::factory()->create(['km_atual' => 1000, 'status' => 'disponivel']);

        $response = $this->postJson('/api/checkins', [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
            'turno' => 'dia',
            'km_saida' => 1000,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('checkins', ['motorista_id' => $motorista->id, 'status' => 'ativo']);
        $this->assertDatabaseHas('veiculos', ['id' => $veiculo->id, 'status' => 'em_uso']);
    }

    public function test_segundo_checkin_do_mesmo_motorista_retorna_erro(): void
    {
        $this->loginAdmin();
        $motorista = Motorista::factory()->create();
        $veiculo1 = Veiculo::factory()->create(['km_atual' => 1000]);
        $veiculo2 = Veiculo::factory()->create(['km_atual' => 2000]);

        Checkin::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo1->id,
            'status' => 'ativo',
            'km_saida' => 1000,
        ]);

        $this->postJson('/api/checkins', [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo2->id,
            'turno' => 'dia',
            'km_saida' => 2000,
        ])->assertUnprocessable();
    }

    public function test_km_saida_menor_que_atual_retorna_erro(): void
    {
        $this->loginAdmin();
        $motorista = Motorista::factory()->create();
        $veiculo = Veiculo::factory()->create(['km_atual' => 5000]);

        $this->postJson('/api/checkins', [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
            'turno' => 'dia',
            'km_saida' => 100,
        ])->assertUnprocessable();
    }

    public function test_operador_nao_visualiza_checkins_de_outro_motorista(): void
    {
        $usuario = $this->loginOperador();
        $meuMotorista = Motorista::factory()->create();
        $outroMotorista = Motorista::factory()->create();
        $usuario->update(['motorista_id' => $meuMotorista->id]);

        $meuCheckin = Checkin::factory()->create(['motorista_id' => $meuMotorista->id]);
        $checkinDeOutro = Checkin::factory()->create(['motorista_id' => $outroMotorista->id]);

        $response = $this->getJson('/api/checkins')->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($meuCheckin->id));
        $this->assertFalse($ids->contains($checkinDeOutro->id));
    }

    public function test_operador_nao_visualiza_detalhe_de_checkin_de_outro_motorista(): void
    {
        $usuario = $this->loginOperador();
        $meuMotorista = Motorista::factory()->create();
        $outroMotorista = Motorista::factory()->create();
        $usuario->update(['motorista_id' => $meuMotorista->id]);

        $checkinDeOutro = Checkin::factory()->create(['motorista_id' => $outroMotorista->id]);

        $this->getJson("/api/checkins/{$checkinDeOutro->id}")->assertForbidden();
    }

    public function test_checkout_com_sucesso(): void
    {
        $this->loginAdmin();
        $motorista = Motorista::factory()->create();
        $veiculo = Veiculo::factory()->create(['km_atual' => 1000, 'status' => 'em_uso']);

        $checkin = Checkin::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
            'km_saida' => 1000,
            'status' => 'ativo',
        ]);

        $this->patchJson("/api/checkins/{$checkin->id}/checkout", [
            'km_retorno' => 1200,
        ])->assertOk();

        $this->assertDatabaseHas('checkins', ['id' => $checkin->id, 'status' => 'encerrado']);
        $this->assertDatabaseHas('veiculos', ['id' => $veiculo->id, 'status' => 'disponivel']);
    }

    public function test_operador_nao_pode_fazer_checkout_com_viagem_em_andamento(): void
    {
        $usuario = $this->loginOperador();
        $motorista = Motorista::factory()->create();
        $usuario->update(['motorista_id' => $motorista->id]);
        $veiculo = Veiculo::factory()->create(['km_atual' => 1000, 'status' => 'em_uso']);

        $checkin = Checkin::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
            'km_saida' => 1000,
            'status' => 'ativo',
        ]);

        Viagem::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
            'checkin_id' => $checkin->id,
            'km_saida' => 1000,
            'status' => 'em_andamento',
        ]);

        $this->patchJson("/api/checkins/{$checkin->id}/checkout", [
            'km_retorno' => 1200,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('checkins', ['id' => $checkin->id, 'status' => 'ativo']);
    }
}
