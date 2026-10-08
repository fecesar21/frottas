<?php

namespace Tests\Feature\MotivoViagem;

use App\Models\MotivoViagem;
use App\Models\Solicitacao;
use App\Models\Usuario;
use App\Models\Viagem;
use App\Notifications\NovaSolicitacaoDisponivel;
use App\Notifications\NovaSolicitacaoTransporte;
use App\Notifications\NovaViagemDesignada;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MotivoViagemRotulosTest extends TestCase
{
    public function test_viagem_e_solicitacao_expoem_motivo_nome(): void
    {
        Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'admin']));

        $viagem = Viagem::factory()->create(['motivo_viagem' => 'servicos_administrativos']);
        // O show de viagens tem o parâmetro de rota "viagen" (singularização do
        // apiResource) e não faz binding; por isso a verificação usa a listagem.
        $this->getJson('/api/viagens')
            ->assertOk()
            ->assertJsonPath('data.0.motivo_nome', 'Serviços Administrativos Diversos');

        $s = Solicitacao::factory()->create(['motivo' => 'tfd']);
        $this->getJson("/api/solicitacoes/{$s->id}")
            ->assertOk()
            ->assertJsonPath('data.motivo_nome', MotivoViagem::rotulo('tfd'));
    }

    public function test_notificacoes_mostram_nome_do_motivo(): void
    {
        $s = Solicitacao::factory()->create(['motivo' => 'buscar_medico']);
        $u = Usuario::factory()->create();
        $nome = MotivoViagem::rotulo('buscar_medico');
        $this->assertNotSame('buscar_medico', $nome);

        $this->assertContains("Motivo: {$nome}", (new NovaSolicitacaoTransporte($s))->toMail($u)->introLines);
        $this->assertContains("Motivo: {$nome}", (new NovaSolicitacaoDisponivel($s))->toMail($u)->introLines);

        $this->assertSame($nome, (new NovaSolicitacaoTransporte($s))->toArray($u)['motivo_nome']);
        $this->assertSame($nome, (new NovaSolicitacaoDisponivel($s))->toArray($u)['motivo_nome']);
        $this->assertSame($nome, (new NovaViagemDesignada($s))->toArray($u)['motivo_nome']);
    }

    public function test_relatorio_viagens_por_motivo_usa_nome_do_cadastro(): void
    {
        Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'admin']));
        Viagem::factory()->create(['motivo_viagem' => 'tfd', 'saida_at' => '2026-06-02 10:00:00']);

        $linhas = $this->getJson('/api/relatorios/dashboard/graficos?mes=6&ano=2026')
            ->assertOk()->json('viagens_por_motivo');

        $this->assertSame(MotivoViagem::rotulo('tfd'), $linhas[0]['motivo']);
    }

    public function test_listener_da_fila_limpa_cache_de_rotulos(): void
    {
        $this->assertSame('TFD', MotivoViagem::rotulo('tfd')); // aquece o cache
        DB::table('motivos_viagem')->where('codigo', 'tfd')->update(['nome' => 'Nome Novo']);
        $this->assertSame('TFD', MotivoViagem::rotulo('tfd')); // cache obsoleto

        Event::dispatch(new JobProcessing('sync', new SyncJob(app(), '{}', 'sync', 'default')));

        $this->assertSame('Nome Novo', MotivoViagem::rotulo('tfd'));
    }
}
