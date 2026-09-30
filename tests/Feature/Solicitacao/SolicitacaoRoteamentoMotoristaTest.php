<?php

namespace Tests\Feature\Solicitacao;

use App\Models\Checkin;
use App\Models\Motorista;
use App\Models\Solicitacao;
use App\Models\Unidade;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Notifications\NovaSolicitacaoDisponivel;
use App\Notifications\NovaSolicitacaoTransporte;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SolicitacaoRoteamentoMotoristaTest extends TestCase
{
    private function motoristaAtivo(string $modelo, ?Unidade $unidade = null, bool $comCheckin = true): Usuario
    {
        $motorista = Motorista::factory()->create();
        $veiculo = Veiculo::factory()->create(['modelo' => $modelo]);
        if ($unidade) {
            $motorista->unidades()->attach($unidade->id);
            $veiculo->unidades()->attach($unidade->id);
        }
        if ($comCheckin) {
            Checkin::factory()->create(['motorista_id' => $motorista->id, 'veiculo_id' => $veiculo->id]);
        }

        return Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $motorista->id]);
    }

    private function loginSolicitante(?Unidade $unidade = null): Usuario
    {
        $usuario = $this->loginOperador();
        $usuario->update(['unidade_id' => $unidade?->id]);

        return $usuario;
    }

    private function payloadTransferencia(): array
    {
        return [
            'motivo' => 'transferencia_paciente',
            'origem_tipo' => 'unidade',
            'origem_id' => Unidade::factory()->create()->id,
            'destino_tipo' => 'unidade',
            'destino_id' => Unidade::factory()->create()->id,
            'numero_atendimento' => 123,
        ];
    }

    public function test_transferencia_do_pai_notifica_apenas_motorista_pai_com_ambulancia_pai(): void
    {
        Notification::fake();
        $pai = Unidade::factory()->create(['nome' => 'PAI - Pronto Atendimento']);
        $upa = Unidade::factory()->create(['nome' => 'UPA Centro']);

        $motoristaPai = $this->motoristaAtivo('Ambulância PAI', $pai);
        $motoristaUpa = $this->motoristaAtivo('AMBULÂNCIA UPA', $upa);
        $semCheckin = $this->motoristaAtivo('AMBULÂNCIA PAI', $pai, comCheckin: false);
        $outroVeiculo = $this->motoristaAtivo('FIORINO', $pai);
        $admin = Usuario::factory()->admin()->create();

        $this->loginSolicitante($pai);
        $this->postJson('/api/solicitacoes', $this->payloadTransferencia())->assertCreated();

        Notification::assertSentTo($motoristaPai, NovaSolicitacaoDisponivel::class);
        Notification::assertNotSentTo([$motoristaUpa, $semCheckin, $outroVeiculo], NovaSolicitacaoDisponivel::class);
        Notification::assertSentTo($admin, NovaSolicitacaoTransporte::class);
    }

    public function test_transferencia_da_upa_notifica_motorista_upa(): void
    {
        Notification::fake();
        $pai = Unidade::factory()->create(['nome' => 'PAI']);
        $upa = Unidade::factory()->create(['nome' => 'UPA']);
        $motoristaPai = $this->motoristaAtivo('AMBULÂNCIA PAI', $pai);
        $motoristaUpa = $this->motoristaAtivo('AMBULÂNCIA UPA', $upa);

        $this->loginSolicitante($upa);
        $this->postJson('/api/solicitacoes', $this->payloadTransferencia())->assertCreated();

        Notification::assertSentTo($motoristaUpa, NovaSolicitacaoDisponivel::class);
        Notification::assertNotSentTo($motoristaPai, NovaSolicitacaoDisponivel::class);
    }

    public function test_motivos_de_apoio_notificam_carros_leves_de_qualquer_unidade(): void
    {
        Notification::fake();
        $upa = Unidade::factory()->create(['nome' => 'UPA']);
        $fiorino = $this->motoristaAtivo('Fiorino Furgão');
        $strada = $this->motoristaAtivo('STRADA', Unidade::factory()->create());
        $ambulancia = $this->motoristaAtivo('AMBULÂNCIA UPA', $upa);

        $this->loginSolicitante($upa);
        $this->postJson('/api/solicitacoes', ['motivo' => 'buscar_medico', 'cidade' => 'Curitiba'])->assertCreated();

        Notification::assertSentTo([$fiorino, $strada], NovaSolicitacaoDisponivel::class);
        Notification::assertNotSentTo($ambulancia, NovaSolicitacaoDisponivel::class);
    }

    public function test_tfd_nao_notifica_motoristas(): void
    {
        Notification::fake();
        $this->motoristaAtivo('COROLLA');
        $admin = Usuario::factory()->admin()->create();

        $this->loginSolicitante();
        $this->postJson('/api/solicitacoes', ['motivo' => 'tfd'])->assertCreated();

        Notification::assertNothingSentTo(Usuario::where('motorista_id', '!=', null)->first());
        Notification::assertSentTo($admin, NovaSolicitacaoTransporte::class);
    }

    public function test_motorista_elegivel_assume_e_cria_viagem(): void
    {
        $usuario = $this->motoristaAtivo('DUSTER');
        $solicitacao = Solicitacao::factory()->create(['motivo' => 'buscar_medico', 'status' => 'aberto']);
        $this->withToken($usuario->createToken('t')->plainTextToken);

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/assumir", ['km_saida' => 5000])
            ->assertOk()
            ->assertJsonPath('data.status', 'em_trajeto');

        $this->assertDatabaseHas('viagens', ['motorista_id' => $usuario->motorista_id, 'km_saida' => 5000]);
    }

    public function test_segundo_motorista_nao_assume_solicitacao_ja_assumida(): void
    {
        $primeiro = $this->motoristaAtivo('DUSTER');
        $segundo = $this->motoristaAtivo('COROLLA');
        $solicitacao = Solicitacao::factory()->create(['motivo' => 'buscar_medico', 'status' => 'aberto']);

        $this->withToken($primeiro->createToken('t')->plainTextToken)
            ->patchJson("/api/solicitacoes/{$solicitacao->id}/assumir", ['km_saida' => 100])->assertOk();

        $this->withToken($segundo->createToken('t')->plainTextToken)
            ->patchJson("/api/solicitacoes/{$solicitacao->id}/assumir", ['km_saida' => 100])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_motorista_inelegivel_nao_assume(): void
    {
        $usuario = $this->motoristaAtivo('AMBULÂNCIA UPA');
        $solicitacao = Solicitacao::factory()->create(['motivo' => 'tfd', 'status' => 'aberto']);

        $this->withToken($usuario->createToken('t')->plainTextToken)
            ->patchJson("/api/solicitacoes/{$solicitacao->id}/assumir", ['km_saida' => 100])
            ->assertUnprocessable()->assertJsonValidationErrors('motorista');

        $this->assertSame('aberto', $solicitacao->fresh()->status);
    }

    public function test_assumir_sem_km_e_sem_viagem_ativa_nao_altera_solicitacao(): void
    {
        $usuario = $this->motoristaAtivo('STRADA');
        $solicitacao = Solicitacao::factory()->create(['motivo' => 'buscar_medico', 'status' => 'aberto']);

        $this->withToken($usuario->createToken('t')->plainTextToken)
            ->patchJson("/api/solicitacoes/{$solicitacao->id}/assumir")
            ->assertUnprocessable()->assertJsonValidationErrors('km_saida');

        $this->assertSame('aberto', $solicitacao->fresh()->status);
    }

    public function test_motorista_em_viagem_assume_e_entra_na_fila(): void
    {
        $usuario = $this->motoristaAtivo('FIORINO');
        Viagem::factory()->create(['motorista_id' => $usuario->motorista_id, 'status' => 'em_andamento']);
        $solicitacao = Solicitacao::factory()->create(['motivo' => 'buscar_medico', 'status' => 'aberto']);

        $this->withToken($usuario->createToken('t')->plainTextToken)
            ->patchJson("/api/solicitacoes/{$solicitacao->id}/assumir")
            ->assertOk()
            ->assertJsonPath('data.status', 'aguardando_finalizacao_trajeto');
    }
}
