<?php

namespace Tests\Feature\Checkin;

use App\Models\Checkin;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Models\VeiculoManutencao;
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

        Viagem::factory()->concluida()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
            'checkin_id' => $checkin->id,
            'km_saida' => 1000,
            'km_chegada' => 1200,
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

    private function checkinAtivo(int $kmSaida = 1000): Checkin
    {
        $veiculo = Veiculo::factory()->create(['km_atual' => $kmSaida, 'status' => 'em_uso']);

        return Checkin::factory()->create([
            'motorista_id' => Motorista::factory()->create()->id,
            'veiculo_id' => $veiculo->id,
            'km_saida' => $kmSaida,
            'checkin_at' => now()->subHour(),
            'status' => 'ativo',
        ]);
    }

    public function test_sem_viagem_km_retorno_diferente_da_saida_retorna_erro(): void
    {
        $this->loginAdmin();
        $checkin = $this->checkinAtivo();

        $this->patchJson("/api/checkins/{$checkin->id}/checkout", ['km_retorno' => 1050])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('km_retorno');

        $this->assertDatabaseHas('checkins', ['id' => $checkin->id, 'status' => 'ativo']);
    }

    public function test_sem_viagem_km_retorno_igual_a_saida_permite_checkout(): void
    {
        $this->loginAdmin();
        $checkin = $this->checkinAtivo();

        $this->patchJson("/api/checkins/{$checkin->id}/checkout", ['km_retorno' => 1000])->assertOk();
    }

    /** Manutenção no veículo do check-in: entrou na oficina e saiu no KM indicado. */
    private function manutencao(Checkin $checkin, int $kmEntrada, int $kmSaida, string $inicio): void
    {
        VeiculoManutencao::create([
            'veiculo_id' => $checkin->veiculo_id,
            'inicio' => $inicio,
            'fim' => now(),
            'origem' => 'gestor',
            'km_entrada' => $kmEntrada,
            'km_saida' => $kmSaida,
        ]);
    }

    public function test_sem_viagem_km_fixo_inclui_km_rodado_na_manutencao(): void
    {
        $this->loginAdmin();
        $checkin = $this->checkinAtivo();
        $this->manutencao($checkin, 1010, 1030, now()->subMinutes(30));

        $this->getJson("/api/checkins/{$checkin->id}")->assertJsonPath('data.km_retorno_fixo', 1030);
        $this->patchJson("/api/checkins/{$checkin->id}/checkout", ['km_retorno' => 1000])->assertUnprocessable();
        $this->patchJson("/api/checkins/{$checkin->id}/checkout", ['km_retorno' => 1030])->assertOk();
    }

    public function test_manutencao_entre_viagens_entra_no_km_esperado_sem_contar_viagem_duas_vezes(): void
    {
        $this->loginAdmin();
        $checkin = $this->checkinAtivo();
        // Viagem 1000→1080, oficina 1080→1100 (ida + teste), viagem 1100→1150.
        Viagem::factory()->concluida()->create([
            'motorista_id' => $checkin->motorista_id, 'veiculo_id' => $checkin->veiculo_id, 'checkin_id' => $checkin->id,
            'km_saida' => 1000, 'km_chegada' => 1080, 'saida_at' => now()->subMinutes(50),
        ]);
        $this->manutencao($checkin, 1090, 1100, now()->subMinutes(30));
        Viagem::factory()->concluida()->create([
            'motorista_id' => $checkin->motorista_id, 'veiculo_id' => $checkin->veiculo_id, 'checkin_id' => $checkin->id,
            'km_saida' => 1100, 'km_chegada' => 1150, 'saida_at' => now()->subMinutes(10),
        ]);

        $this->patchJson("/api/checkins/{$checkin->id}/checkout", ['km_retorno' => 1150])->assertOk();
        $this->assertDatabaseHas('checkins', ['id' => $checkin->id, 'km_retorno_esperado' => 1150, 'divergencia_km' => 0]);
    }

    public function test_gestao_com_viagem_em_andamento_exige_km_de_chegada(): void
    {
        $this->loginAdmin();
        $checkin = $this->checkinAtivo();
        $viagem = Viagem::factory()->create([
            'motorista_id' => $checkin->motorista_id, 'veiculo_id' => $checkin->veiculo_id, 'checkin_id' => $checkin->id,
            'km_saida' => 1000, 'status' => 'em_andamento',
        ]);

        $this->getJson("/api/checkins/{$checkin->id}")->assertJsonPath('data.viagem_em_andamento.id', $viagem->id);

        $this->patchJson("/api/checkins/{$checkin->id}/checkout", ['km_retorno' => 1060])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('km_chegada_viagem');

        $this->assertDatabaseHas('viagens', ['id' => $viagem->id, 'status' => 'em_andamento']);
        $this->assertDatabaseHas('checkins', ['id' => $checkin->id, 'status' => 'ativo']);
    }

    public function test_gestao_encerra_viagem_em_andamento_no_checkout(): void
    {
        $this->loginAdmin();
        $checkin = $this->checkinAtivo();
        $viagem = Viagem::factory()->create([
            'motorista_id' => $checkin->motorista_id, 'veiculo_id' => $checkin->veiculo_id, 'checkin_id' => $checkin->id,
            'km_saida' => 1000, 'status' => 'em_andamento',
        ]);

        $this->patchJson("/api/checkins/{$checkin->id}/checkout", ['km_chegada_viagem' => 1060, 'km_retorno' => 1060])
            ->assertOk();

        $this->assertDatabaseHas('viagens', ['id' => $viagem->id, 'status' => 'concluida', 'km_chegada' => 1060]);
        $this->assertDatabaseHas('checkins', [
            'id' => $checkin->id, 'status' => 'encerrado', 'km_retorno' => 1060, 'km_retorno_esperado' => 1060, 'divergencia_km' => 0,
        ]);
    }

    public function test_resource_informa_km_retorno_fixo_sem_viagem(): void
    {
        $this->loginAdmin();
        $checkin = $this->checkinAtivo();

        $this->getJson("/api/checkins/{$checkin->id}")
            ->assertOk()
            ->assertJsonPath('data.km_retorno_fixo', 1000);
    }

    /** Check-in em 1000 com duas viagens (1000→1080 e 1100→1150): esperado 1130. */
    private function checkinComViagens(): Checkin
    {
        $checkin = $this->checkinAtivo();
        foreach ([[1000, 1080], [1100, 1150]] as [$saida, $chegada]) {
            Viagem::factory()->concluida()->create([
                'motorista_id' => $checkin->motorista_id,
                'veiculo_id' => $checkin->veiculo_id,
                'checkin_id' => $checkin->id,
                'km_saida' => $saida,
                'km_chegada' => $chegada,
            ]);
        }

        return $checkin;
    }

    public function test_km_igual_a_saida_mais_viagens_encerra_sem_divergencia(): void
    {
        $this->loginAdmin();
        $checkin = $this->checkinComViagens();

        $this->getJson("/api/checkins/{$checkin->id}")->assertJsonPath('data.km_retorno_esperado', 1130);

        $this->patchJson("/api/checkins/{$checkin->id}/checkout", ['km_retorno' => 1130])->assertOk();

        $this->assertDatabaseHas('checkins', [
            'id' => $checkin->id,
            'status' => 'encerrado',
            'km_retorno_esperado' => 1130,
            'divergencia_km' => 0,
            'justificativa_divergencia_km' => null,
        ]);
    }

    public function test_km_divergente_sem_justificativa_retorna_erro(): void
    {
        $this->loginAdmin();
        $checkin = $this->checkinComViagens();

        $this->patchJson("/api/checkins/{$checkin->id}/checkout", ['km_retorno' => 1150])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('justificativa_divergencia_km');

        $this->assertDatabaseHas('checkins', ['id' => $checkin->id, 'status' => 'ativo']);
    }

    public function test_km_divergente_com_justificativa_registra_divergencia(): void
    {
        $this->loginAdmin();
        $checkin = $this->checkinComViagens();

        $this->patchJson("/api/checkins/{$checkin->id}/checkout", [
            'km_retorno' => 1150,
            'justificativa_divergencia_km' => 'Deslocamento até o posto sem viagem registrada',
        ])->assertOk()
            ->assertJsonPath('data.divergencia_km', 20);

        $this->assertDatabaseHas('checkins', [
            'id' => $checkin->id,
            'status' => 'encerrado',
            'km_retorno' => 1150,
            'km_retorno_esperado' => 1130,
            'divergencia_km' => 20,
            'justificativa_divergencia_km' => 'Deslocamento até o posto sem viagem registrada',
        ]);
    }
}
