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

    public function test_gestor_aceita_solicitacao_e_cria_viagem_vinculada(): void
    {
        $this->loginGestor();
        $solicitacao = Solicitacao::factory()->create(['motivo' => 'tfd', 'status' => 'aberto']);
        $motorista = Motorista::factory()->create();
        $veiculo = Veiculo::factory()->create();

        $response = $this->patchJson("/api/solicitacoes/{$solicitacao->id}/aceitar", [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'em_trajeto');
        $this->assertDatabaseHas('solicitacoes', ['id' => $solicitacao->id, 'status' => 'em_trajeto']);
        $this->assertDatabaseHas('viagens', ['motorista_id' => $motorista->id, 'veiculo_id' => $veiculo->id]);
    }

    public function test_aceitar_com_motorista_em_outro_veiculo_troca_checkin_automaticamente(): void
    {
        $this->loginGestor();
        $solicitacao = Solicitacao::factory()->create(['motivo' => 'tfd', 'status' => 'aberto']);
        $motorista = Motorista::factory()->create();
        $veiculoAntigo = Veiculo::factory()->create(['status' => 'em_uso', 'km_atual' => 1000]);
        $veiculoNovo = Veiculo::factory()->create(['status' => 'disponivel', 'km_atual' => 2000]);

        $checkinAntigo = Checkin::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoAntigo->id,
            'km_saida' => 1000,
            'status' => 'ativo',
        ]);

        $response = $this->patchJson("/api/solicitacoes/{$solicitacao->id}/aceitar", [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoNovo->id,
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'em_trajeto');

        $this->assertDatabaseHas('checkins', [
            'id' => $checkinAntigo->id,
            'status' => 'encerrado',
        ]);
        $this->assertDatabaseHas('checkins', [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoNovo->id,
            'status' => 'ativo',
        ]);
        $this->assertDatabaseHas('veiculos', ['id' => $veiculoAntigo->id, 'status' => 'disponivel']);
        $this->assertDatabaseHas('veiculos', ['id' => $veiculoNovo->id, 'status' => 'em_uso']);

        $novoCheckin = Checkin::where('motorista_id', $motorista->id)->where('status', 'ativo')->first();
        $this->assertDatabaseHas('viagens', [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoNovo->id,
            'checkin_id' => $novoCheckin->id,
        ]);
    }

    public function test_aceitar_com_troca_de_veiculo_apos_viagem_anterior_ja_concluida_preenche_km_retorno(): void
    {
        $this->loginGestor();
        $solicitacao = Solicitacao::factory()->create(['motivo' => 'tfd', 'status' => 'aberto']);
        $motorista = Motorista::factory()->create();
        $veiculoAntigo = Veiculo::factory()->create(['status' => 'em_uso', 'km_atual' => 1050]);
        $veiculoNovo = Veiculo::factory()->create(['status' => 'disponivel', 'km_atual' => 2000]);

        $checkinAntigo = Checkin::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoAntigo->id,
            'km_saida' => 1000,
            'status' => 'ativo',
        ]);

        Viagem::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoAntigo->id,
            'km_saida' => 1000,
            'km_chegada' => 1050,
            'chegada_at' => now(),
            'status' => 'concluida',
        ]);

        $response = $this->patchJson("/api/solicitacoes/{$solicitacao->id}/aceitar", [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoNovo->id,
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'em_trajeto');
        $this->assertDatabaseHas('checkins', [
            'id' => $checkinAntigo->id,
            'status' => 'encerrado',
            'km_retorno' => 1050,
        ]);
    }

    public function test_aceitar_motorista_com_viagem_em_andamento_fica_aguardando_finalizacao(): void
    {
        $this->loginGestor();
        $solicitacao = Solicitacao::factory()->create(['motivo' => 'tfd', 'status' => 'aberto']);
        $motorista = Motorista::factory()->create();
        $veiculoAtual = Veiculo::factory()->create(['status' => 'em_uso']);
        $veiculoNovo = Veiculo::factory()->create(['status' => 'disponivel']);

        Viagem::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoAtual->id,
            'status' => 'em_andamento',
        ]);

        $response = $this->patchJson("/api/solicitacoes/{$solicitacao->id}/aceitar", [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoNovo->id,
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'aguardando_finalizacao_trajeto');
        $this->assertDatabaseHas('solicitacoes', [
            'id' => $solicitacao->id,
            'status' => 'aguardando_finalizacao_trajeto',
            'motorista_pendente_id' => $motorista->id,
            'veiculo_pendente_id' => $veiculoNovo->id,
        ]);
        $this->assertDatabaseMissing('viagens', ['motorista_id' => $motorista->id, 'veiculo_id' => $veiculoNovo->id]);
    }

    public function test_finalizar_trajeto_efetiva_solicitacao_pendente_com_troca_de_veiculo(): void
    {
        $this->loginGestor();
        $solicitacao = Solicitacao::factory()->create(['motivo' => 'tfd', 'status' => 'aberto']);
        $motorista = Motorista::factory()->create();
        $veiculoAtual = Veiculo::factory()->create(['status' => 'em_uso', 'km_atual' => 1000]);
        $veiculoNovo = Veiculo::factory()->create(['status' => 'disponivel', 'km_atual' => 500]);

        $checkinAtual = Checkin::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoAtual->id,
            'km_saida' => 1000,
            'status' => 'ativo',
        ]);

        $viagemEmAndamento = Viagem::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoAtual->id,
            'km_saida' => 1000,
            'status' => 'em_andamento',
        ]);

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/aceitar", [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoNovo->id,
        ])->assertOk()->assertJsonPath('data.status', 'aguardando_finalizacao_trajeto');

        $this->patchJson("/api/viagens/{$viagemEmAndamento->id}/chegada", ['km_chegada' => 1050])
            ->assertOk();

        $this->assertDatabaseHas('solicitacoes', [
            'id' => $solicitacao->id,
            'status' => 'em_trajeto',
            'motorista_pendente_id' => null,
            'veiculo_pendente_id' => null,
        ]);

        $this->assertDatabaseHas('checkins', ['id' => $checkinAtual->id, 'status' => 'encerrado', 'km_retorno' => 1050]);
        $this->assertDatabaseHas('checkins', [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoNovo->id,
            'status' => 'ativo',
        ]);

        $this->assertDatabaseHas('viagens', [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoNovo->id,
            'status' => 'em_andamento',
        ]);
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
