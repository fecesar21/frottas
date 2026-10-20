<?php

namespace Tests\Feature\Solicitacao;

use App\Models\Solicitacao;
use App\Models\Viagem;
use Tests\TestCase;

class SolicitacaoVinculoCancelamentoTest extends TestCase
{
    private function transferencia(array $attrs = []): Solicitacao
    {
        return Solicitacao::factory()->create(array_merge([
            'motivo' => 'transferencia_paciente',
            'numero_atendimento' => 123456,
            'status' => 'aberto',
        ], $attrs));
    }

    public function test_admin_vincula_solicitacao_a_viagem_concluida_com_mesmo_atendimento(): void
    {
        $this->loginAdmin();
        $solicitacao = $this->transferencia();
        $viagem = Viagem::factory()->create([
            'motivo_viagem' => 'transferencia_paciente',
            'numero_atendimento' => 123456,
            'status' => 'concluida',
        ]);

        $this->getJson("/api/solicitacoes/{$solicitacao->id}/viagens-candidatas")
            ->assertOk()
            ->assertJsonPath('data.0.id', $viagem->id);

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/vincular-viagem", ['viagem_id' => $viagem->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'finalizado');

        $this->assertSame($viagem->id, $solicitacao->fresh()->viagem_id);
    }

    public function test_vinculo_a_viagem_em_andamento_fica_em_trajeto(): void
    {
        $this->loginAdmin();
        $solicitacao = $this->transferencia();
        $viagem = Viagem::factory()->create([
            'motivo_viagem' => 'transferencia_paciente',
            'numero_atendimento' => 123456,
            'status' => 'em_andamento',
        ]);

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/vincular-viagem", ['viagem_id' => $viagem->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'em_trajeto');
    }

    public function test_transferencia_com_atendimento_diferente_nao_vincula(): void
    {
        $this->loginAdmin();
        $solicitacao = $this->transferencia();
        $viagem = Viagem::factory()->create([
            'motivo_viagem' => 'transferencia_paciente',
            'numero_atendimento' => 654321,
            'status' => 'concluida',
        ]);

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/vincular-viagem", ['viagem_id' => $viagem->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['viagem_id']);

        $this->assertNull($solicitacao->fresh()->viagem_id);
    }

    public function test_viagem_ja_vinculada_a_outra_solicitacao_nao_vincula(): void
    {
        $this->loginAdmin();
        $viagem = Viagem::factory()->create([
            'motivo_viagem' => 'transferencia_paciente',
            'numero_atendimento' => 123456,
            'status' => 'concluida',
        ]);
        $this->transferencia(['viagem_id' => $viagem->id, 'status' => 'finalizado']);
        $solicitacao = $this->transferencia();

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/vincular-viagem", ['viagem_id' => $viagem->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['viagem_id']);
    }

    public function test_operador_nao_vincula(): void
    {
        $this->loginOperador();
        $solicitacao = $this->transferencia();
        $viagem = Viagem::factory()->create(['motivo_viagem' => 'transferencia_paciente', 'numero_atendimento' => 123456]);

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/vincular-viagem", ['viagem_id' => $viagem->id])
            ->assertForbidden();
    }

    public function test_solicitante_cancela_a_propria_solicitacao_em_aberto(): void
    {
        $usuario = $this->loginAs('solicitante');
        $solicitacao = $this->transferencia(['usuario_id' => $usuario->id]);

        $this->patchJson("/api/solicitacoes/{$solicitacao->id}/cancelar")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelado');
    }

    public function test_nao_cancela_solicitacao_com_viagem_iniciada_ou_realizada(): void
    {
        $usuario = $this->loginAs('solicitante');

        foreach (['em_trajeto', 'finalizado'] as $status) {
            $solicitacao = $this->transferencia([
                'usuario_id' => $usuario->id,
                'status' => $status,
                'viagem_id' => Viagem::factory()->create()->id,
            ]);

            $this->patchJson("/api/solicitacoes/{$solicitacao->id}/cancelar")
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['status']);

            $this->assertSame($status, $solicitacao->fresh()->status);
        }
    }
}
