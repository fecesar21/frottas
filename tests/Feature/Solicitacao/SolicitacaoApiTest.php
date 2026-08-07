<?php

namespace Tests\Feature\Solicitacao;

use App\Models\Checkin;
use App\Models\Motorista;
use App\Models\Solicitacao;
use App\Models\Unidade;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Notifications\NovaSolicitacaoTransporte;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SolicitacaoApiTest extends TestCase
{
    public function test_operador_cria_solicitacao_tfd(): void
    {
        $this->loginOperador();

        $response = $this->postJson('/api/solicitacoes', ['motivo' => 'tfd']);

        $response->assertCreated()->assertJsonPath('data.motivo', 'tfd');
        $this->assertDatabaseHas('solicitacoes', ['motivo' => 'tfd', 'status' => 'aberto']);
    }

    public function test_transferencia_paciente_exige_origem_destino_e_atendimento(): void
    {
        $this->loginOperador();

        $this->postJson('/api/solicitacoes', ['motivo' => 'transferencia_paciente'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['origem_unidade_id', 'destino_unidade_id', 'numero_atendimento']);

        $origem = Unidade::factory()->create();
        $destino = Unidade::factory()->create();

        $this->postJson('/api/solicitacoes', [
            'motivo' => 'transferencia_paciente',
            'origem_unidade_id' => $origem->id,
            'destino_unidade_id' => $destino->id,
            'numero_atendimento' => 12345,
        ])->assertCreated();
    }

    public function test_operador_so_ve_as_proprias_solicitacoes(): void
    {
        $usuario = $this->loginOperador();
        Solicitacao::factory()->count(2)->create(['usuario_id' => $usuario->id]);
        Solicitacao::factory()->count(3)->create();

        $this->getJson('/api/solicitacoes')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_gestor_designa_motorista_e_solicitacao_fica_pendente_motorista(): void
    {
        $this->loginGestor();
        $solicitacao = Solicitacao::factory()->create(['motivo' => 'tfd', 'status' => 'aberto']);
        $motorista = Motorista::factory()->create();
        $veiculo = Veiculo::factory()->create();

        $response = $this->patchJson("/api/solicitacoes/{$solicitacao->id}/aceitar", [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'pendente_motorista');
        $this->assertDatabaseHas('solicitacoes', [
            'id' => $solicitacao->id,
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motorista->id,
            'veiculo_pendente_id' => $veiculo->id,
        ]);
        $this->assertDatabaseMissing('viagens', ['motorista_id' => $motorista->id, 'veiculo_id' => $veiculo->id]);
    }

    public function test_motorista_aceita_sem_viagem_ativa_informando_km_saida_cria_viagem(): void
    {
        $motorista = Motorista::factory()->create();
        $usuarioMotorista = Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $motorista->id]);
        $veiculo = Veiculo::factory()->create();
        $solicitacao = Solicitacao::factory()->create([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motorista->id,
            'veiculo_pendente_id' => $veiculo->id,
        ]);

        $token = $usuarioMotorista->createToken('test')->plainTextToken;
        $this->withToken($token);

        $response = $this->patchJson("/api/solicitacoes/{$solicitacao->id}/motorista-aceitar", ['km_saida' => 1500]);

        $response->assertOk()->assertJsonPath('data.status', 'em_trajeto');
        $this->assertDatabaseHas('viagens', [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
            'km_saida' => 1500,
            'status' => 'em_andamento',
        ]);
    }

    public function test_motorista_aceita_com_viagem_ativa_fica_aguardando_sem_km(): void
    {
        $motorista = Motorista::factory()->create();
        $usuarioMotorista = Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $motorista->id]);
        $veiculoAtual = Veiculo::factory()->create(['status' => 'em_uso']);
        $veiculoNovo = Veiculo::factory()->create(['status' => 'disponivel']);

        Viagem::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoAtual->id,
            'status' => 'em_andamento',
        ]);

        $solicitacao = Solicitacao::factory()->create([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motorista->id,
            'veiculo_pendente_id' => $veiculoNovo->id,
        ]);

        $token = $usuarioMotorista->createToken('test')->plainTextToken;
        $this->withToken($token);

        $response = $this->patchJson("/api/solicitacoes/{$solicitacao->id}/motorista-aceitar", []);

        $response->assertOk()->assertJsonPath('data.status', 'aguardando_finalizacao_trajeto');
        $this->assertDatabaseMissing('viagens', ['motorista_id' => $motorista->id, 'veiculo_id' => $veiculoNovo->id]);
    }

    public function test_motorista_aceita_sem_viagem_ativa_e_sem_km_falha_422(): void
    {
        $motorista = Motorista::factory()->create();
        $usuarioMotorista = Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $motorista->id]);
        $veiculo = Veiculo::factory()->create();
        $solicitacao = Solicitacao::factory()->create([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motorista->id,
            'veiculo_pendente_id' => $veiculo->id,
        ]);

        $token = $usuarioMotorista->createToken('test')->plainTextToken;
        $this->withToken($token);

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/motorista-aceitar", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['km_saida']);
    }

    public function test_motorista_recusa_com_motivo(): void
    {
        $motorista = Motorista::factory()->create();
        $usuarioMotorista = Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $motorista->id]);
        $veiculo = Veiculo::factory()->create();
        $solicitacao = Solicitacao::factory()->create([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motorista->id,
            'veiculo_pendente_id' => $veiculo->id,
        ]);

        $token = $usuarioMotorista->createToken('test')->plainTextToken;
        $this->withToken($token);

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/motorista-recusar", ['motivo' => 'Veículo com problema mecânico'])
            ->assertOk()
            ->assertJsonPath('data.status', 'recusada');

        $this->assertDatabaseHas('solicitacoes', [
            'id' => $solicitacao->id,
            'status' => 'recusada',
            'motivo_recusa' => 'Veículo com problema mecânico',
            'motorista_pendente_id' => null,
            'veiculo_pendente_id' => null,
        ]);
    }

    public function test_gestor_pode_redesignar_apos_recusa(): void
    {
        // Setup: create a solicitacao with recusada status
        $motorista = Motorista::factory()->create();
        $veiculo = Veiculo::factory()->create();
        $solicitacao = Solicitacao::factory()->create([
            'status' => 'recusada',
            'motorista_pendente_id' => null,
            'veiculo_pendente_id' => null,
            'motivo_recusa' => 'Veículo com problema',
        ]);

        // Now test that a manager can redesignate
        $outroMotorista = Motorista::factory()->create();
        $outroVeiculo = Veiculo::factory()->create();

        $this->loginGestor();

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/aceitar", [
            'motorista_id' => $outroMotorista->id,
            'veiculo_id' => $outroVeiculo->id,
        ])->assertOk()->assertJsonPath('data.status', 'pendente_motorista');
    }

    public function test_motorista_nao_pode_aceitar_solicitacao_de_outro_motorista(): void
    {
        $motoristaDesignado = Motorista::factory()->create();
        $outroMotorista = Motorista::factory()->create();
        $usuarioOutroMotorista = Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $outroMotorista->id]);
        $veiculo = Veiculo::factory()->create();
        $solicitacao = Solicitacao::factory()->create([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motoristaDesignado->id,
            'veiculo_pendente_id' => $veiculo->id,
        ]);

        $token = $usuarioOutroMotorista->createToken('test')->plainTextToken;
        $this->withToken($token);

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/motorista-aceitar", ['km_saida' => 100])
            ->assertForbidden();
    }

    public function test_finalizar_viagem_finaliza_solicitacao_vinculada(): void
    {
        $this->loginGestor();
        $viagem = Viagem::factory()->create(['km_saida' => 1000]);
        $solicitacao = Solicitacao::factory()->create(['status' => 'em_trajeto', 'viagem_id' => $viagem->id]);

        $this->patchJson("/api/viagens/{$viagem->id}/chegada", ['km_chegada' => 1050])
            ->assertOk();

        $this->assertDatabaseHas('solicitacoes', ['id' => $solicitacao->id, 'status' => 'finalizado']);
    }

    public function test_cria_solicitacao_notifica_admins_e_gestor_da_unidade(): void
    {
        Notification::fake();

        $unidade = \App\Models\Unidade::factory()->create();
        $operador = Usuario::factory()->create(['perfil' => 'operador', 'unidade_id' => $unidade->id]);
        $adminGlobal = Usuario::factory()->admin()->create();
        $gestorMesmaUnidade = Usuario::factory()->create(['perfil' => 'gestor', 'unidade_id' => $unidade->id]);
        $gestorOutraUnidade = Usuario::factory()->create(['perfil' => 'gestor', 'unidade_id' => \App\Models\Unidade::factory()->create()->id]);

        $token = $operador->createToken('test')->plainTextToken;
        $this->withToken($token);

        $this->postJson('/api/solicitacoes', [
            'motivo' => 'tfd',
            'cidade' => 'Curitiba',
        ])->assertCreated();

        Notification::assertSentTo($adminGlobal, NovaSolicitacaoTransporte::class);
        Notification::assertSentTo($gestorMesmaUnidade, NovaSolicitacaoTransporte::class);
        Notification::assertNotSentTo($gestorOutraUnidade, NovaSolicitacaoTransporte::class);
        Notification::assertNotSentTo($operador, NovaSolicitacaoTransporte::class);
    }
}
