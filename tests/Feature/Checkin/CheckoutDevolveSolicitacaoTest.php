<?php

namespace Tests\Feature\Checkin;

use App\Models\Checkin;
use App\Models\Motorista;
use App\Models\Solicitacao;
use App\Models\Veiculo;
use Tests\TestCase;

class CheckoutDevolveSolicitacaoTest extends TestCase
{
    public function test_checkout_devolve_ao_gestor_as_solicitacoes_aguardando_o_motorista(): void
    {
        $usuario = $this->loginOperador();
        $motorista = Motorista::factory()->create();
        $usuario->update(['motorista_id' => $motorista->id]);
        $veiculo = Veiculo::factory()->create();
        $checkin = Checkin::factory()->create(['motorista_id' => $motorista->id, 'veiculo_id' => $veiculo->id, 'status' => 'ativo']);
        $pendente = Solicitacao::factory()->create(['status' => 'pendente_motorista', 'motorista_pendente_id' => $motorista->id, 'veiculo_pendente_id' => $veiculo->id]);
        $naFila = Solicitacao::factory()->create(['status' => 'aguardando_finalizacao_trajeto', 'motorista_pendente_id' => $motorista->id, 'veiculo_pendente_id' => $veiculo->id]);
        $deOutro = Solicitacao::factory()->create(['status' => 'pendente_motorista', 'motorista_pendente_id' => Motorista::factory()->create()->id]);

        $this->patchJson("/api/checkins/{$checkin->id}/checkout", ['km_retorno' => $checkin->km_saida])->assertOk();

        foreach ([$pendente, $naFila] as $s) {
            $this->assertDatabaseHas('solicitacoes', ['id' => $s->id, 'status' => 'aberto', 'motorista_pendente_id' => null]);
        }
        $this->assertDatabaseHas('solicitacoes', ['id' => $deOutro->id, 'status' => 'pendente_motorista']);
    }

    public function test_checkout_de_um_dos_checkins_duplos_mantem_a_solicitacao(): void
    {
        $usuario = $this->loginOperador();
        $motorista = Motorista::factory()->create(['permite_checkin_duplo' => true]);
        $usuario->update(['motorista_id' => $motorista->id]);
        $checkin = Checkin::factory()->create(['motorista_id' => $motorista->id, 'veiculo_id' => Veiculo::factory()->create()->id, 'status' => 'ativo']);
        Checkin::factory()->create(['motorista_id' => $motorista->id, 'veiculo_id' => Veiculo::factory()->create()->id, 'status' => 'ativo']);
        $pendente = Solicitacao::factory()->create(['status' => 'pendente_motorista', 'motorista_pendente_id' => $motorista->id]);

        $this->patchJson("/api/checkins/{$checkin->id}/checkout", ['km_retorno' => $checkin->km_saida])->assertOk();

        $this->assertDatabaseHas('solicitacoes', ['id' => $pendente->id, 'status' => 'pendente_motorista']);
    }

    public function test_gestor_nao_designa_motorista_sem_checkin_ativo(): void
    {
        $this->loginGestor();
        $solicitacao = Solicitacao::factory()->create(['status' => 'aberto']);

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/aceitar", [
            'motorista_id' => Motorista::factory()->create()->id,
            'veiculo_id' => Veiculo::factory()->create()->id,
        ])->assertStatus(422)->assertJsonValidationErrors('motorista_id');
    }
}
