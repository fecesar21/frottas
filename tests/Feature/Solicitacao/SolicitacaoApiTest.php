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
use App\Notifications\NovaViagemDesignada;
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

    public function test_motorista_ve_apenas_solicitacoes_designadas_para_ele(): void
    {
        $motorista = Motorista::factory()->create();
        $usuarioMotorista = Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $motorista->id]);
        Solicitacao::factory()->create(['status' => 'pendente_motorista', 'motorista_pendente_id' => $motorista->id]);
        Solicitacao::factory()->create(['status' => 'pendente_motorista', 'motorista_pendente_id' => Motorista::factory()->create()->id]);

        $token = $usuarioMotorista->createToken('test')->plainTextToken;
        $this->withToken($token);

        $this->getJson('/api/solicitacoes')
            ->assertOk()
            ->assertJsonCount(1, 'data');
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

    public function test_motorista_aceita_com_viagem_ativa_e_km_informado_nao_cria_segunda_viagem(): void
    {
        // Regressão do finding Critical 1: mesmo que o client informe km_saida
        // (estado desatualizado / corrida), nunca pode nascer uma segunda Viagem
        // em_andamento para o mesmo motorista — a solicitação deve ir para a fila.
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

        $response = $this->patchJson("/api/solicitacoes/{$solicitacao->id}/motorista-aceitar", ['km_saida' => 999]);

        $response->assertOk()->assertJsonPath('data.status', 'aguardando_finalizacao_trajeto');
        $this->assertSame(1, Viagem::where('motorista_id', $motorista->id)->where('status', 'em_andamento')->count());
        $this->assertDatabaseMissing('viagens', ['motorista_id' => $motorista->id, 'veiculo_id' => $veiculoNovo->id]);
    }

    public function test_duas_tentativas_de_aceite_para_o_mesmo_motorista_nunca_duplicam_viagem_em_andamento(): void
    {
        // Regressão do finding Critical 1: duas chamadas motorista-aceitar em
        // sequência rápida para o mesmo motorista (ex.: duplo clique / retry de
        // rede) nunca podem resultar em duas Viagens em_andamento simultâneas.
        $motorista = Motorista::factory()->create();
        $usuarioMotorista = Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $motorista->id]);
        $veiculo = Veiculo::factory()->create();
        $solicitacao1 = Solicitacao::factory()->create([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motorista->id,
            'veiculo_pendente_id' => $veiculo->id,
        ]);
        $solicitacao2 = Solicitacao::factory()->create([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motorista->id,
            'veiculo_pendente_id' => Veiculo::factory()->create()->id,
        ]);

        $token = $usuarioMotorista->createToken('test')->plainTextToken;
        $this->withToken($token);

        $this->patchJson("/api/solicitacoes/{$solicitacao1->id}/motorista-aceitar", ['km_saida' => 1000])
            ->assertOk()
            ->assertJsonPath('data.status', 'em_trajeto');

        // Segunda solicitação chega logo em seguida, motorista já em viagem —
        // mesmo informando km_saida, deve ser enfileirada, nunca criar outra viagem.
        $this->patchJson("/api/solicitacoes/{$solicitacao2->id}/motorista-aceitar", ['km_saida' => 1100])
            ->assertOk()
            ->assertJsonPath('data.status', 'aguardando_finalizacao_trajeto');

        $this->assertSame(1, Viagem::where('motorista_id', $motorista->id)->where('status', 'em_andamento')->count());
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

    public function test_motorista_recusa_com_motivo_gestor_pode_redesignar(): void
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

        $this->loginGestor();
        $this->app['auth']->forgetGuards();
        $outroMotorista = Motorista::factory()->create();
        $outroVeiculo = Veiculo::factory()->create();

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

        $unidade = Unidade::factory()->create();
        $operador = Usuario::factory()->create(['perfil' => 'operador', 'unidade_id' => $unidade->id]);
        $adminGlobal = Usuario::factory()->admin()->create();
        $gestorMesmaUnidade = Usuario::factory()->create(['perfil' => 'gestor', 'unidade_id' => $unidade->id]);
        $gestorOutraUnidade = Usuario::factory()->create(['perfil' => 'gestor', 'unidade_id' => Unidade::factory()->create()->id]);

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

    public function test_recurso_expoe_motivo_recusa_para_gestor(): void
    {
        $this->loginGestor();
        $solicitacao = Solicitacao::factory()->create([
            'status' => 'recusada',
            'motivo_recusa' => 'Sem combustível suficiente',
        ]);

        $this->getJson("/api/solicitacoes/{$solicitacao->id}")
            ->assertOk()
            ->assertJsonPath('data.motivo_recusa', 'Sem combustível suficiente');
    }

    public function test_solicitante_nao_ve_motivo_recusa_de_solicitacao_propria(): void
    {
        // Regressão do finding Critical 2a: o motivo de recusa nunca pode
        // vazar para o solicitante original.
        $usuario = $this->loginOperador();
        $solicitacao = Solicitacao::factory()->create([
            'usuario_id' => $usuario->id,
            'status' => 'recusada',
            'motivo_recusa' => 'Sem combustível suficiente',
        ]);

        $response = $this->getJson("/api/solicitacoes/{$solicitacao->id}")->assertOk();

        $this->assertArrayNotHasKey('motivo_recusa', $response->json('data'));
    }

    public function test_usuario_sem_relacao_com_a_solicitacao_recebe_403_no_show(): void
    {
        // Regressão do finding Critical 2b: show() precisa checar propriedade.
        $this->loginOperador();
        $solicitacao = Solicitacao::factory()->create([
            'usuario_id' => Usuario::factory()->create()->id,
            'status' => 'aberto',
        ]);

        $this->getJson("/api/solicitacoes/{$solicitacao->id}")->assertForbidden();
    }

    public function test_motorista_designado_pode_ver_a_solicitacao_no_show(): void
    {
        $motorista = Motorista::factory()->create();
        $usuarioMotorista = Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $motorista->id]);
        $solicitacao = Solicitacao::factory()->create([
            'status' => 'pendente_motorista',
            'motorista_pendente_id' => $motorista->id,
            'veiculo_pendente_id' => Veiculo::factory()->create()->id,
        ]);

        $token = $usuarioMotorista->createToken('test')->plainTextToken;
        $this->withToken($token);

        $this->getJson("/api/solicitacoes/{$solicitacao->id}")->assertOk();
    }

    public function test_conclusao_de_viagem_notifica_motorista_para_informar_km_da_fila_sem_criar_viagem(): void
    {
        Notification::fake();

        $motorista = Motorista::factory()->create();
        $usuarioMotorista = Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $motorista->id]);
        $veiculoAtual = Veiculo::factory()->create(['status' => 'em_uso', 'km_atual' => 1000]);
        $veiculoNovo = Veiculo::factory()->create(['status' => 'disponivel', 'km_atual' => 500]);

        Checkin::factory()->create([
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

        $solicitacaoNaFila = Solicitacao::factory()->create([
            'status' => 'aguardando_finalizacao_trajeto',
            'motorista_pendente_id' => $motorista->id,
            'veiculo_pendente_id' => $veiculoNovo->id,
        ]);

        $this->loginGestor();
        $this->patchJson("/api/viagens/{$viagemEmAndamento->id}/chegada", ['km_chegada' => 1050])
            ->assertOk();

        $this->assertDatabaseHas('solicitacoes', [
            'id' => $solicitacaoNaFila->id,
            'status' => 'aguardando_finalizacao_trajeto',
        ]);
        $this->assertDatabaseMissing('viagens', ['motorista_id' => $motorista->id, 'veiculo_id' => $veiculoNovo->id]);
        Notification::assertSentTo($usuarioMotorista, NovaViagemDesignada::class, function ($notification) use ($solicitacaoNaFila, $usuarioMotorista) {
            return $notification->toArray($usuarioMotorista)['solicitacao_id'] === $solicitacaoNaFila->id
                && $notification->toArray($usuarioMotorista)['fila'] === true;
        });

        $this->app['auth']->forgetGuards();
        $token = $usuarioMotorista->createToken('test')->plainTextToken;
        $this->withToken($token);

        // km_chegada da viagem anterior (1050) e km_saida informado pelo motorista
        // para a NOVA viagem (1200) são valores DIFERENTES — prova que cada um vai
        // para o campo certo (regressão do finding Important 3, que misturava os dois).
        $this->patchJson("/api/solicitacoes/{$solicitacaoNaFila->id}/motorista-aceitar", ['km_saida' => 1200])
            ->assertOk()
            ->assertJsonPath('data.status', 'em_trajeto');

        $this->assertDatabaseHas('viagens', [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoNovo->id,
            'km_saida' => 1200,
            'status' => 'em_andamento',
        ]);
        $this->assertDatabaseHas('checkins', [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculoAtual->id,
            'status' => 'encerrado',
            'km_retorno' => 1050,
        ]);
    }
}
