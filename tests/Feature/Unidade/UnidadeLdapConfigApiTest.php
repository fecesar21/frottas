<?php

namespace Tests\Feature\Unidade;

use App\Models\Unidade;
use App\Models\Usuario;
use Tests\TestCase;

class UnidadeLdapConfigApiTest extends TestCase
{
    private function admin(): Usuario
    {
        return Usuario::factory()->create(['perfil' => 'admin']);
    }

    public function test_nao_admin_recebe_403(): void
    {
        $unidade = Unidade::factory()->create();
        $gestor = Usuario::factory()->create(['perfil' => 'gestor']);

        $this->actingAs($gestor, 'sanctum')
            ->getJson("/api/unidades/{$unidade->id}/ldap-config")
            ->assertForbidden();
    }

    public function test_show_sem_configuracao_retorna_404(): void
    {
        $unidade = Unidade::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/unidades/{$unidade->id}/ldap-config")
            ->assertNotFound();
    }

    public function test_upsert_cria_configuracao_e_nunca_retorna_senha(): void
    {
        $unidade = Unidade::factory()->create();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/unidades/{$unidade->id}/ldap-config", [
                'host' => '10.0.0.5',
                'port' => 636,
                'base_dn' => 'DC=empresa,DC=local',
                'username' => 'svc@empresa.local',
                'password' => 'segredo123',
                'use_ssl' => true,
                'use_starttls' => false,
                'unidade_attribute' => 'department',
                'valores_ad' => ['HOSP-CENTRO'],
                'ativo' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('host', '10.0.0.5')
            ->assertJsonPath('senha_configurada', true)
            ->assertJsonMissingPath('password');

        $this->assertDatabaseHas('unidade_ldap_configuracoes', [
            'unidade_id' => $unidade->id,
            'host' => '10.0.0.5',
        ]);
    }

    public function test_criar_sem_senha_retorna_422(): void
    {
        $unidade = Unidade::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/unidades/{$unidade->id}/ldap-config", [
                'host' => '10.0.0.5',
                'port' => 636,
                'base_dn' => 'DC=empresa,DC=local',
                'username' => 'svc@empresa.local',
                'unidade_attribute' => 'department',
                'valores_ad' => ['HOSP-CENTRO'],
            ])
            ->assertStatus(422);
    }

    public function test_atualizar_com_senha_em_branco_mantem_senha_anterior(): void
    {
        $unidade = Unidade::factory()->create();
        $config = \App\Models\UnidadeLdapConfiguracao::factory()->create([
            'unidade_id' => $unidade->id,
            'password' => 'senha-original',
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/unidades/{$unidade->id}/ldap-config", [
                'host' => 'novo-host',
                'port' => $config->port,
                'base_dn' => $config->base_dn,
                'username' => $config->username,
                'unidade_attribute' => $config->unidade_attribute,
                'valores_ad' => $config->valores_ad,
            ])
            ->assertOk()
            ->assertJsonPath('host', 'novo-host');

        $this->assertEquals('senha-original', $config->fresh()->password);
    }

    public function test_valores_ad_vazio_falha_validacao(): void
    {
        $unidade = Unidade::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/unidades/{$unidade->id}/ldap-config", [
                'host' => '10.0.0.5',
                'port' => 636,
                'base_dn' => 'DC=empresa,DC=local',
                'username' => 'svc@empresa.local',
                'password' => 'segredo',
                'unidade_attribute' => 'department',
                'valores_ad' => [],
            ])
            ->assertStatus(422);
    }

    public function test_destroy_remove_configuracao(): void
    {
        $unidade = Unidade::factory()->create();
        \App\Models\UnidadeLdapConfiguracao::factory()->create(['unidade_id' => $unidade->id]);

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/unidades/{$unidade->id}/ldap-config")
            ->assertOk();

        $this->assertDatabaseMissing('unidade_ldap_configuracoes', ['unidade_id' => $unidade->id]);
    }
}
