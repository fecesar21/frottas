<?php

namespace Tests\Feature\DashboardGerencial;

use App\Models\Solicitacao;
use App\Models\Usuario;
use App\Models\Viagem;
use Carbon\Carbon;
use Tests\TestCase;

class DashboardGerencialApiTest extends TestCase
{
    private function transferencia(Carbon $autorizacao, int $minSolicitacao, int $minInicio, int $minFim, array $extra = []): Solicitacao
    {
        $criada = $autorizacao->copy()->addMinutes($minSolicitacao);
        $viagem = Viagem::factory()->create([
            'saida_at' => $criada->copy()->addMinutes($minInicio),
            'chegada_at' => $criada->copy()->addMinutes($minInicio + $minFim),
            'km_chegada' => 2000,
            'status' => 'concluida',
        ]);

        $s = Solicitacao::factory()->create(array_merge([
            'motivo' => 'transferencia_paciente',
            'status' => 'finalizado',
            'viagem_id' => $viagem->id,
            'autorizacao_referencia_em' => $autorizacao,
        ], $extra));
        $s->forceFill(['created_at' => $criada])->save();

        return $s;
    }

    public function test_calcula_total_e_tempos_medios_do_mes(): void
    {
        $this->loginAs('dashboard');
        $base = Carbon::create(2026, 9, 10, 8, 0);
        $this->transferencia($base, 4, 6, 10);            // total 20
        $this->transferencia($base->copy()->addDay(), 6, 10, 14); // total 30
        $this->transferencia(Carbon::create(2026, 8, 10, 8), 5, 5, 5); // outro mês
        Solicitacao::factory()->create(['motivo' => 'tfd', 'status' => 'aberto']);

        $this->getJson('/api/relatorios/dashboard-gerencial/transferencias?mes=9&ano=2026')
            ->assertOk()
            ->assertJsonPath('acordo_minutos', 30)
            ->assertJsonPath('total_transferencias', 2)
            ->assertJsonPath('medias_minutos.autorizacao_fim', 25)
            ->assertJsonPath('medias_minutos.autorizacao_solicitacao', 5)
            ->assertJsonPath('medias_minutos.solicitacao_inicio', 8)
            ->assertJsonPath('medias_minutos.inicio_fim', 12);
    }

    public function test_perfil_dashboard_nao_acessa_outras_rotas(): void
    {
        $this->loginAs('dashboard');
        $this->getJson('/api/veiculos')->assertForbidden();
        $this->getJson('/api/relatorios/dashboard')->assertForbidden();
        $this->getJson('/api/auth/me')->assertOk();
    }

    public function test_operador_nao_acessa_dashboard_gerencial(): void
    {
        $this->loginOperador();
        $this->getJson('/api/relatorios/dashboard-gerencial/transferencias')->assertForbidden();
    }

    public function test_admin_cria_usuario_com_perfil_dashboard(): void
    {
        $this->loginAdmin();
        $this->postJson('/api/usuarios', [
            'nome' => 'Painel TV',
            'cpf' => '52998224725',
            'senha' => '123456',
            'perfil' => 'dashboard',
        ])->assertCreated();

        $this->assertSame('dashboard', Usuario::where('nome', 'Painel TV')->value('perfil'));
    }
}
