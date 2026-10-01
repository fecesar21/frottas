<?php

namespace Tests\Feature\Checkin;

use App\Models\Checkin;
use App\Models\ChecklistVeiculo;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Support\Plantao;
use Tests\TestCase;

class CheckinDuploApiTest extends TestCase
{
    private function operadorCom(array $atributosMotorista = []): array
    {
        $usuario = $this->loginOperador();
        $motorista = Motorista::factory()->create($atributosMotorista);
        $usuario->update(['motorista_id' => $motorista->id]);

        return [$usuario, $motorista];
    }

    private function checkinAtivo(Motorista $motorista, Veiculo $veiculo): Checkin
    {
        $checkin = Checkin::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
            'status' => 'ativo',
        ]);

        ChecklistVeiculo::create([
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'checkin_id' => $checkin->id,
            'data_referencia' => Plantao::atual()['data'],
            'turno' => Plantao::atual()['turno'],
            'status' => 'enviado',
            'enviado_at' => now(),
        ]);

        return $checkin;
    }

    private function novoCheckin(Veiculo $veiculo)
    {
        return $this->postJson('/api/checkins', [
            'veiculo_id' => $veiculo->id,
            'km_saida' => $veiculo->km_atual,
            'turno' => 'noite',
        ]);
    }

    private function novaViagem(array $extra = [])
    {
        return $this->postJson('/api/viagens', array_merge([
            'motorista_id' => $extra['motorista_id'],
            'origem' => 'PAI',
            'destino' => 'Hospital',
            'km_saida' => 5000,
            'motivo_viagem' => 'buscar_medico',
        ], $extra));
    }

    public function test_motorista_sem_permissao_continua_limitado_a_um_checkin(): void
    {
        [, $motorista] = $this->operadorCom();
        $this->checkinAtivo($motorista, Veiculo::factory()->create());

        $this->novoCheckin(Veiculo::factory()->create(['km_atual' => 1000]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('motorista_id');
    }

    public function test_motorista_com_permissao_faz_dois_checkins_e_e_bloqueado_no_terceiro(): void
    {
        [, $motorista] = $this->operadorCom(['permite_checkin_duplo' => true]);
        $this->checkinAtivo($motorista, Veiculo::factory()->create());

        $this->novoCheckin(Veiculo::factory()->create(['km_atual' => 1000]))->assertCreated();
        $this->novoCheckin(Veiculo::factory()->create(['km_atual' => 1000]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('motorista_id');

        $this->assertSame(2, $motorista->checkinsAtivos()->count());
    }

    public function test_segundo_checkin_nao_pode_usar_veiculo_em_uso(): void
    {
        [, $motorista] = $this->operadorCom(['permite_checkin_duplo' => true]);
        $veiculo = Veiculo::factory()->create();
        $this->checkinAtivo($motorista, $veiculo);

        $this->novoCheckin($veiculo)->assertStatus(422)->assertJsonValidationErrors('veiculo_id');
    }

    public function test_com_dois_checkins_viagem_usa_o_veiculo_escolhido(): void
    {
        [, $motorista] = $this->operadorCom(['permite_checkin_duplo' => true]);
        $ambulancia = Veiculo::factory()->create(['km_atual' => 4000]);
        $administrativo = Veiculo::factory()->create(['km_atual' => 4000]);
        $this->checkinAtivo($motorista, $ambulancia);
        $checkinAdm = $this->checkinAtivo($motorista, $administrativo);

        $this->novaViagem(['motorista_id' => $motorista->id, 'veiculo_id' => $administrativo->id])->assertCreated();

        $this->assertDatabaseHas('viagens', [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $administrativo->id,
            'checkin_id' => $checkinAdm->id,
        ]);
    }

    public function test_com_dois_checkins_veiculo_fora_dos_checkins_retorna_422(): void
    {
        [, $motorista] = $this->operadorCom(['permite_checkin_duplo' => true]);
        $this->checkinAtivo($motorista, Veiculo::factory()->create());
        $this->checkinAtivo($motorista, Veiculo::factory()->create());

        $this->novaViagem(['motorista_id' => $motorista->id, 'veiculo_id' => Veiculo::factory()->create()->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('veiculo_id');
    }

    public function test_checkout_de_um_veiculo_permitido_com_viagem_em_andamento_no_outro(): void
    {
        [, $motorista] = $this->operadorCom(['permite_checkin_duplo' => true]);
        $ambulancia = Veiculo::factory()->create();
        $administrativo = Veiculo::factory()->create();
        $checkinAmb = $this->checkinAtivo($motorista, $ambulancia);
        $checkinAdm = $this->checkinAtivo($motorista, $administrativo);

        Viagem::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $ambulancia->id,
            'checkin_id' => $checkinAmb->id,
            'status' => 'em_andamento',
        ]);

        $this->patchJson("/api/checkins/{$checkinAdm->id}/checkout", [])->assertOk();
        $this->patchJson("/api/checkins/{$checkinAmb->id}/checkout", [])->assertStatus(422);
    }

    public function test_login_retorna_lista_de_checkins_ativos(): void
    {
        [$usuario, $motorista] = $this->operadorCom(['permite_checkin_duplo' => true]);
        $this->checkinAtivo($motorista, Veiculo::factory()->create());
        $this->checkinAtivo($motorista, Veiculo::factory()->create());

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonCount(2, 'user.checkins_ativos')
            ->assertJsonPath('user.permite_checkin_duplo', true);
    }
}
