<?php

namespace Tests\Feature\Auth;

use App\Models\Abastecimento;
use App\Models\Unidade;
use Tests\TestCase;

class RestricaoSolicitanteTest extends TestCase
{
    public function test_solicitante_acessa_solicitacoes(): void
    {
        $this->loginAs('solicitante');

        $this->getJson('/api/solicitacoes')->assertOk();
    }

    public function test_solicitante_acessa_unidades_em_modo_leitura(): void
    {
        $this->loginAs('solicitante');
        Unidade::factory()->create();

        $this->getJson('/api/unidades')->assertOk();
    }

    public function test_solicitante_nao_pode_deletar_unidade(): void
    {
        $this->loginAs('solicitante');
        $unidade = Unidade::factory()->create();

        $this->deleteJson("/api/unidades/{$unidade->id}")->assertStatus(403);
    }

    public function test_solicitante_nao_pode_criar_motorista(): void
    {
        $this->loginAs('solicitante');

        $this->postJson('/api/motoristas', [])->assertStatus(403);
    }

    public function test_solicitante_nao_pode_deletar_abastecimento(): void
    {
        $this->loginAs('solicitante');
        $abastecimento = Abastecimento::factory()->create(['combustivel' => 'diesel_s10']);

        $this->deleteJson("/api/abastecimentos/{$abastecimento->id}")->assertStatus(403);
    }

    public function test_solicitante_nao_acessa_relatorio_dashboard(): void
    {
        $this->loginAs('solicitante');

        $this->getJson('/api/relatorios/dashboard')->assertStatus(403);
    }

    public function test_gestor_nao_e_afetado_pela_restricao_de_solicitante(): void
    {
        $this->loginAs('gestor');

        $this->getJson('/api/motoristas')->assertOk();
    }
}
