<?php

namespace Tests\Feature\Veiculo;

use App\Models\Checkin;
use App\Models\Motorista;
use App\Models\Solicitacao;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Models\VeiculoManutencao;
use App\Models\Viagem;
use App\Notifications\VeiculoManutencaoNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ManutencaoMotoristaApiTest extends TestCase
{
    private function motoristaComCheckin(array $veiculoAttrs = []): array
    {
        $motorista = Motorista::factory()->create();
        $veiculo = Veiculo::factory()->create(array_merge(['status' => 'em_uso', 'km_atual' => 1000], $veiculoAttrs));
        $checkin = Checkin::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
            'km_saida' => 1000,
        ]);
        $usuario = Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $motorista->id]);
        $this->app['auth']->forgetGuards();
        $this->withToken($usuario->createToken('t')->plainTextToken);

        return [$usuario, $motorista, $veiculo, $checkin];
    }

    public function test_motorista_com_checkin_coloca_veiculo_em_manutencao(): void
    {
        Notification::fake();
        $admin = Usuario::factory()->create(['perfil' => 'admin']);
        [$usuario, , $veiculo] = $this->motoristaComCheckin();

        $this->postJson("/api/veiculos/{$veiculo->id}/manutencao", [
            'tipo' => 'troca_oleo',
            'km_entrada' => 1050,
            'local' => 'Oficina Central',
        ])->assertOk()
            ->assertJsonPath('data.status', 'manutencao')
            ->assertJsonPath('data.manutencao_atual.tipo', 'troca_oleo');

        $registro = VeiculoManutencao::sole();
        $this->assertSame('motorista', $registro->origem);
        $this->assertSame($usuario->id, $registro->aberta_por_id);
        $this->assertSame(1050, $registro->km_entrada);
        $this->assertSame(1050, $veiculo->fresh()->km_atual);
        Notification::assertSentTo($admin, VeiculoManutencaoNotification::class);
    }

    public function test_motivo_obrigatorio_quando_tipo_outro(): void
    {
        [, , $veiculo] = $this->motoristaComCheckin();

        $this->postJson("/api/veiculos/{$veiculo->id}/manutencao", ['tipo' => 'outro'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('motivo');
    }

    public function test_motorista_sem_checkin_no_veiculo_recebe_403(): void
    {
        $this->motoristaComCheckin();
        $outro = Veiculo::factory()->create(['status' => 'disponivel']);

        $this->postJson("/api/veiculos/{$outro->id}/manutencao", ['tipo' => 'revisao'])->assertForbidden();
        $this->assertSame(0, VeiculoManutencao::count());
    }

    public function test_viagem_em_andamento_exige_km_de_chegada(): void
    {
        [, $motorista, $veiculo] = $this->motoristaComCheckin();
        Viagem::factory()->create(['motorista_id' => $motorista->id, 'veiculo_id' => $veiculo->id, 'km_saida' => 1000]);

        $this->postJson("/api/veiculos/{$veiculo->id}/manutencao", ['tipo' => 'mecanica'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('km_chegada');

        $this->assertSame('em_uso', $veiculo->fresh()->status);
    }

    public function test_viagem_em_andamento_e_finalizada_com_km_de_chegada(): void
    {
        [, $motorista, $veiculo] = $this->motoristaComCheckin();
        $viagem = Viagem::factory()->create(['motorista_id' => $motorista->id, 'veiculo_id' => $veiculo->id, 'km_saida' => 1000]);

        $this->postJson("/api/veiculos/{$veiculo->id}/manutencao", ['tipo' => 'mecanica', 'km_chegada' => 1080])
            ->assertOk();

        $viagem->refresh();
        $this->assertSame('concluida', $viagem->status);
        $this->assertSame(1080, (int) $viagem->km_chegada);
        $this->assertSame(1080, VeiculoManutencao::sole()->km_entrada);
        $this->assertSame('manutencao', $veiculo->fresh()->status);
    }

    public function test_solicitacao_designada_ao_veiculo_volta_para_a_fila(): void
    {
        Notification::fake();
        [, $motorista, $veiculo] = $this->motoristaComCheckin();
        $solicitacao = Solicitacao::factory()->create([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motorista->id,
            'veiculo_pendente_id' => $veiculo->id,
        ]);

        $this->postJson("/api/veiculos/{$veiculo->id}/manutencao", ['tipo' => 'troca_pneus'])->assertOk();

        $solicitacao->refresh();
        $this->assertSame('aberto', $solicitacao->status);
        $this->assertNull($solicitacao->motorista_pendente_id);
        $this->assertNull($solicitacao->veiculo_pendente_id);
    }

    public function test_veiculo_em_manutencao_nao_registra_viagem(): void
    {
        [, $motorista, $veiculo] = $this->motoristaComCheckin();
        $this->postJson("/api/veiculos/{$veiculo->id}/manutencao", ['tipo' => 'revisao'])->assertOk();

        $this->postJson('/api/viagens', [
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'origem' => 'A',
            'destino' => 'B',
            'km_saida' => 1000,
        ])->assertUnprocessable();

        $this->assertSame(0, Viagem::count());
    }

    public function test_gestor_nao_designa_veiculo_em_manutencao(): void
    {
        $veiculo = Veiculo::factory()->create(['status' => 'manutencao']);
        $motorista = Motorista::factory()->create();
        $solicitacao = Solicitacao::factory()->create();
        $this->loginGestor();

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/aceitar", [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
        ])->assertUnprocessable();

        $this->assertSame('aberto', $solicitacao->fresh()->status);
    }

    public function test_checkin_em_veiculo_em_manutencao_e_bloqueado(): void
    {
        $veiculo = Veiculo::factory()->create(['status' => 'manutencao', 'km_atual' => 500]);
        $motorista = Motorista::factory()->create();
        $this->loginGestor();

        $this->postJson('/api/checkins', [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
            'turno' => 'dia',
            'km_saida' => 500,
            'nivel_combustivel_saida' => 50,
        ])->assertUnprocessable();
    }

    public function test_checkout_mantem_veiculo_em_manutencao(): void
    {
        [, , $veiculo, $checkin] = $this->motoristaComCheckin();
        $this->postJson("/api/veiculos/{$veiculo->id}/manutencao", ['tipo' => 'revisao'])->assertOk();

        $this->patchJson("/api/checkins/{$checkin->id}/checkout", ['km_retorno' => 1000])->assertOk();

        $this->assertSame('manutencao', $veiculo->fresh()->status);
        $this->assertNull(VeiculoManutencao::sole()->fim);
    }

    public function test_motorista_retira_veiculo_da_manutencao(): void
    {
        [$usuario, , $veiculo] = $this->motoristaComCheckin();
        $this->postJson("/api/veiculos/{$veiculo->id}/manutencao", ['tipo' => 'revisao', 'km_entrada' => 1010])->assertOk();

        $this->postJson("/api/veiculos/{$veiculo->id}/manutencao/encerrar", ['km_saida' => 1005])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('km_saida');

        $this->postJson("/api/veiculos/{$veiculo->id}/manutencao/encerrar", [
            'km_saida' => 1015,
            'observacao_saida' => 'Óleo e filtro trocados',
        ])->assertOk()->assertJsonPath('data.status', 'em_uso');

        $registro = VeiculoManutencao::sole();
        $this->assertNotNull($registro->fim);
        $this->assertSame($usuario->id, $registro->fechada_por_id);
        $this->assertSame(1015, $registro->km_saida);
        $this->assertNull($veiculo->fresh()->manutencao_inicio);
    }

    public function test_sem_checkin_veiculo_volta_disponivel(): void
    {
        $veiculo = Veiculo::factory()->create(['status' => 'disponivel']);
        $this->loginGestor();

        $this->postJson("/api/veiculos/{$veiculo->id}/manutencao", ['tipo' => 'funilaria_pintura'])->assertOk();
        $this->assertSame('gestor', VeiculoManutencao::sole()->origem);

        $this->postJson("/api/veiculos/{$veiculo->id}/manutencao/encerrar")
            ->assertOk()
            ->assertJsonPath('data.status', 'disponivel');
    }

    public function test_outro_motorista_sem_vinculo_nao_encerra(): void
    {
        $veiculo = Veiculo::factory()->create(['status' => 'disponivel']);
        $this->loginGestor();
        $this->postJson("/api/veiculos/{$veiculo->id}/manutencao", ['tipo' => 'revisao'])->assertOk();

        $this->motoristaComCheckin();
        $this->postJson("/api/veiculos/{$veiculo->id}/manutencao/encerrar")->assertForbidden();
    }

    public function test_flag_do_gestor_grava_tipo_e_origem(): void
    {
        $gestor = $this->loginGestor();
        $veiculo = Veiculo::factory()->create(['status' => 'disponivel']);

        $this->patchJson("/api/veiculos/{$veiculo->id}", [
            'status' => 'manutencao',
            'manutencao_tipo' => 'troca_pneus',
            'manutencao_motivo' => 'Pneu furado',
        ])->assertOk();

        $registro = VeiculoManutencao::sole();
        $this->assertSame('troca_pneus', $registro->tipo);
        $this->assertSame('gestor', $registro->origem);
        $this->assertSame($gestor->id, $registro->aberta_por_id);

        $this->patchJson("/api/veiculos/{$veiculo->id}", ['status' => 'disponivel'])->assertOk();
        $this->assertSame($gestor->id, $registro->fresh()->fechada_por_id);
    }
}
