<?php

namespace Tests\Feature\Relatorio;

use App\Mail\ResumoOperacionalMail;
use App\Models\Motorista;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Models\VeiculoManutencao;
use App\Models\Viagem;
use App\Services\ResumoOperacionalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResumoOperacionalTest extends TestCase
{
    private function viagem(Veiculo $v, Motorista $m, string $saida, int $km, ?string $motivo = null): void
    {
        Viagem::factory()->create([
            'veiculo_id' => $v->id, 'motorista_id' => $m->id, 'motivo_viagem' => $motivo,
            'saida_at' => $saida, 'km_saida' => 1000, 'km_chegada' => 1000 + $km,
            'chegada_at' => Carbon::parse($saida)->addMinutes(20), 'status' => 'concluida',
        ]);
    }

    public function test_agrega_somente_dados_dentro_da_janela(): void
    {
        $this->travelTo('2026-10-02 10:00');
        $v = Veiculo::factory()->create();
        $m = Motorista::factory()->create();

        $this->viagem($v, $m, '2026-10-01 08:00', 100, 'buscar_medico');
        $this->viagem($v, $m, '2026-10-01 18:59', 50);
        $this->viagem($v, $m, '2026-10-01 19:30', 999, 'Fora');

        VeiculoManutencao::factory()->create([
            'veiculo_id' => $v->id, 'inicio' => '2026-10-01 05:00', 'fim' => '2026-10-01 09:00',
        ]);
        VeiculoManutencao::factory()->create([
            'veiculo_id' => $v->id, 'inicio' => '2026-10-01 18:00', 'fim' => null,
        ]);

        $r = app(ResumoOperacionalService::class)
            ->gerar(Carbon::parse('2026-10-01 07:00'), Carbon::parse('2026-10-01 19:00'));

        $this->assertSame(2, $r['totais']['viagens']);
        $this->assertEquals(150, $r['km_por_veiculo'][0]['km']);
        $this->assertEquals(150, $r['km_por_motorista'][0]['km']);
        $this->assertSame(2, $r['viagens_por_motorista'][0]['viagens']);
        $this->assertEqualsCanonicalizing(['Buscar Médico em Outra Cidade', 'Não informado'], array_column($r['viagens_por_motivo'], 'motivo'));
        // 07:00–09:00 (120) + 18:00–19:00 (60)
        $this->assertSame(180, $r['manutencao_por_veiculo'][0]['minutos']);
    }

    public function test_viagem_conta_se_encerrada_ate_30min_apos_o_fim_e_senao_vira_observacao(): void
    {
        $this->travelTo('2026-10-01 20:00');
        $v = Veiculo::factory()->create(['placa' => 'CGL2J92']);
        $m = Motorista::factory()->create(['nome' => 'LUCAS']);
        $base = ['veiculo_id' => $v->id, 'motorista_id' => $m->id];

        // Encerrada 19:10 (dentro da margem): conta viagem e KM.
        Viagem::factory()->create($base + ['saida_at' => '2026-10-01 18:20', 'km_saida' => 124550,
            'km_chegada' => 124560, 'chegada_at' => '2026-10-01 19:10', 'status' => 'concluida']);
        // Encerrada 19:45 (fora da margem): não conta.
        Viagem::factory()->create($base + ['saida_at' => '2026-10-01 18:40', 'km_saida' => 124560,
            'km_chegada' => 124600, 'chegada_at' => '2026-10-01 19:45', 'status' => 'concluida']);
        // Ainda aberta: não conta.
        Viagem::factory()->create($base + ['saida_at' => '2026-10-01 18:50', 'km_saida' => 124600,
            'km_chegada' => null, 'chegada_at' => null, 'status' => 'em_andamento']);

        $r = app(ResumoOperacionalService::class)
            ->gerar(Carbon::parse('2026-10-01 07:00'), Carbon::parse('2026-10-01 18:59:59'));

        $this->assertSame(1, $r['totais']['viagens']);
        $this->assertEquals(10.0, $r['totais']['km']);
        $this->assertCount(2, $r['observacoes']);
        $this->assertStringContainsString('LUCAS', $r['observacoes'][0]);
        $this->assertStringContainsString('CGL2J92', $r['observacoes'][0]);
    }

    public function test_plantao_diurno_enviado_as_20h_para_admins_e_gestores_com_email(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-01 20:00');
        $admin = Usuario::factory()->admin()->create();
        Usuario::factory()->create(['perfil' => 'gestor', 'email' => null]);
        Usuario::factory()->create(['perfil' => 'operador']);

        $this->artisan('relatorio:resumo plantao')->assertSuccessful();

        Mail::assertQueuedCount(1);
        Mail::assertQueued(ResumoOperacionalMail::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email)
                && $mail->dados['titulo'] === 'Resumo do Plantão Diurno de 01/10/2026'
                && $mail->dados['periodo'] === '01/10/2026 07:00 a 01/10/2026 18:59';
        });
    }

    public function test_plantao_noturno_enviado_as_08h_atravessa_meia_noite(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-02 08:00');
        Usuario::factory()->admin()->create();

        $this->artisan('relatorio:resumo plantao')->assertSuccessful();

        Mail::assertQueued(ResumoOperacionalMail::class, fn ($mail) => $mail->dados['periodo'] === '01/10/2026 19:00 a 02/10/2026 06:59'
            && str_contains($mail->dados['titulo'], 'Noturno'));
    }

    public function test_mensal_cobre_mes_anterior_e_gera_pdf(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-01 07:00');
        Usuario::factory()->admin()->create();

        $this->artisan('relatorio:resumo mensal')->assertSuccessful();

        Mail::assertQueued(ResumoOperacionalMail::class, function ($mail) {
            $this->assertStringStartsWith('%PDF', ResumoOperacionalMail::pdf($mail->dados));

            return $mail->dados['periodo'] === '01/09/2026 00:00 a 30/09/2026 23:59'
                && $mail->nomeArquivo === 'resumo-mensal-2026-09.pdf';
        });
    }
}
