<?php

namespace Tests\Feature\DashboardGerencial;

use App\Models\Solicitacao;
use App\Models\Unidade;
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

    public function test_admin_cria_usuario_dashboard_sem_cpf_com_login_e_unidades(): void
    {
        $this->loginAdmin();
        $unidades = Unidade::factory()->count(2)->create();

        $this->postJson('/api/usuarios', [
            'nome' => 'Painel TV',
            'login' => 'Painel.TV',
            'senha' => 'senhaTv1',
            'perfil' => 'dashboard',
            'unidades' => $unidades->pluck('id')->all(),
        ])->assertCreated()
            ->assertJsonPath('data.login', 'painel.tv')
            ->assertJsonPath('data.cpf', null)
            ->assertJsonCount(2, 'data.unidades');
    }

    public function test_perfil_dashboard_exige_login_e_demais_exigem_cpf(): void
    {
        $this->loginAdmin();
        $this->postJson('/api/usuarios', ['nome' => 'X', 'senha' => 'abcdef', 'perfil' => 'dashboard'])
            ->assertJsonValidationErrors('login');
        $this->postJson('/api/usuarios', ['nome' => 'Y', 'senha' => '123456', 'perfil' => 'gestor'])
            ->assertJsonValidationErrors('cpf');
    }

    public function test_login_com_usuario_comum(): void
    {
        Usuario::factory()->create([
            'cpf' => null, 'login' => 'painel.tv', 'perfil' => 'dashboard',
            'senha_hash' => bcrypt('senhaTv1'), 'ativo' => true,
        ]);

        $this->postJson('/api/auth/login', ['usuario' => 'Painel.TV', 'senha' => 'senhaTv1'])
            ->assertOk()
            ->assertJsonPath('user.perfil', 'dashboard');
        $this->postJson('/api/auth/login', ['usuario' => 'painel.tv', 'senha' => 'errada'])
            ->assertUnauthorized();
    }

    public function test_dashboard_ve_somente_unidades_vinculadas(): void
    {
        $usuario = $this->loginAs('dashboard');
        [$a, $b, $c] = Unidade::factory()->count(3)->create()->all();
        $usuario->unidadesDashboard()->sync([$a->id, $b->id]);

        $base = now()->startOfMonth()->addDays(2)->setTime(8, 0);
        $this->transferencia($base, 5, 5, 10, ['unidade_id' => $a->id]);
        $this->transferencia($base, 5, 5, 10, ['unidade_id' => $b->id]);
        $this->transferencia($base, 5, 5, 10, ['unidade_id' => $c->id]);

        $url = '/api/relatorios/dashboard-gerencial/transferencias?mes='.$base->month.'&ano='.$base->year;
        $this->getJson($url)->assertOk()
            ->assertJsonPath('total_transferencias', 2)
            ->assertJsonCount(2, 'unidades');
        $this->getJson($url.'&unidade_id='.$a->id)->assertOk()->assertJsonPath('total_transferencias', 1);
        $this->getJson($url.'&unidade_id='.$c->id)->assertForbidden();
    }
}
