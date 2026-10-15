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
            'motivo_viagem' => 'transferencia_paciente',
            'saida_at' => $criada->copy()->addMinutes($minInicio),
            'chegada_at' => $criada->copy()->addMinutes($minInicio + $minFim),
            'km_chegada' => 2000,
            'status' => 'concluida',
        ]);
        if (isset($extra['unidade_id'])) {
            $viagem->motorista->unidades()->attach($extra['unidade_id']);
        }

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
            ->assertJsonPath('medias_minutos.autorizacao_inicio', 13)
            ->assertJsonPath('medias_minutos.autorizacao_fim', 25)
            ->assertJsonPath('medias_minutos.autorizacao_solicitacao', 5)
            ->assertJsonPath('medias_minutos.solicitacao_inicio', 8)
            ->assertJsonPath('medias_minutos.inicio_fim', 12);
    }

    public function test_agrupa_transferencias_por_origem_e_destino(): void
    {
        $this->loginAs('dashboard');
        $saida = Carbon::create(2026, 9, 5, 9, 0);
        foreach ([['HOSPITAL A', 'HOSPITAL B'], ['HOSPITAL A', 'HOSPITAL B'], ['UPA C', 'HOSPITAL B']] as [$o, $d]) {
            Viagem::factory()->create([
                'motivo_viagem' => 'transferencia_paciente', 'status' => 'concluida',
                'origem' => $o, 'destino' => $d, 'saida_at' => $saida,
            ]);
        }

        $this->getJson('/api/relatorios/dashboard-gerencial/transferencias?mes=9&ano=2026')
            ->assertOk()
            ->assertJsonCount(2, 'por_rota')
            ->assertJsonPath('por_rota.0', ['origem' => 'HOSPITAL A', 'destino' => 'HOSPITAL B', 'total' => 2])
            ->assertJsonPath('por_rota.1', ['origem' => 'UPA C', 'destino' => 'HOSPITAL B', 'total' => 1]);
    }

    public function test_conta_viagem_de_transferencia_lancada_sem_solicitacao(): void
    {
        $this->loginAs('dashboard');
        $saida = Carbon::create(2026, 9, 5, 9, 0);
        Viagem::factory()->create([
            'motivo_viagem' => 'transferencia_paciente', 'status' => 'concluida',
            'saida_at' => $saida, 'chegada_at' => $saida->copy()->addMinutes(40), 'km_chegada' => 2000,
        ]);
        Viagem::factory()->create(['motivo_viagem' => 'tfd', 'saida_at' => $saida]);

        $this->getJson('/api/relatorios/dashboard-gerencial/transferencias?mes=9&ano=2026')
            ->assertOk()
            ->assertJsonPath('total_transferencias', 1)
            ->assertJsonPath('medias_minutos.inicio_fim', 40)
            ->assertJsonPath('medias_minutos.autorizacao_inicio', null);
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

    public function test_admin_cria_usuario_dashboard_com_cpf_generico_e_unidades(): void
    {
        $this->loginAdmin();
        $unidades = Unidade::factory()->count(2)->create();

        $this->postJson('/api/usuarios', [
            'nome' => 'Painel TV',
            'cpf' => '000.000.000-00',
            'senha' => '246813',
            'perfil' => 'dashboard',
            'unidades' => $unidades->pluck('id')->all(),
        ])->assertCreated()
            ->assertJsonPath('data.cpf', '00000000000')
            ->assertJsonCount(2, 'data.unidades');
    }

    public function test_perfil_dashboard_exige_cpf_e_senha_numerica(): void
    {
        $this->loginAdmin();
        $this->postJson('/api/usuarios', ['nome' => 'X', 'senha' => 'abcdef', 'perfil' => 'dashboard'])
            ->assertJsonValidationErrors(['cpf', 'senha']);
    }

    public function test_dashboard_entra_com_cpf_generico(): void
    {
        Usuario::factory()->create([
            'cpf' => '00000000000', 'perfil' => 'dashboard',
            'senha_hash' => bcrypt('246813'), 'ativo' => true,
        ]);

        $this->postJson('/api/auth/login', ['usuario' => '000.000.000-00', 'senha' => '246813'])
            ->assertOk()
            ->assertJsonPath('user.perfil', 'dashboard');
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
