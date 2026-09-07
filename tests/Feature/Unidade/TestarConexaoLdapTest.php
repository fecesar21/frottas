<?php

namespace Tests\Feature\Unidade;

use App\Models\Unidade;
use App\Models\Usuario;
use Tests\TestCase;

class TestarConexaoLdapTest extends TestCase
{
    public function test_testar_conexao_nao_persiste_nada(): void
    {
        $unidade = Unidade::factory()->create();
        $admin = Usuario::factory()->create(['perfil' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/unidades/{$unidade->id}/ldap-config/testar", [
                'host' => 'dc-inexistente.invalido',
                'port' => 636,
                'base_dn' => 'DC=empresa,DC=local',
                'username' => 'svc@empresa.local',
                'password' => 'qualquer',
            ])
            ->assertOk()
            ->assertJsonPath('sucesso', false);

        $this->assertDatabaseMissing('unidade_ldap_configuracoes', ['unidade_id' => $unidade->id]);
    }

    public function test_testar_conexao_exige_admin(): void
    {
        $unidade = Unidade::factory()->create();
        $operador = Usuario::factory()->create(['perfil' => 'operador']);

        $this->actingAs($operador, 'sanctum')
            ->postJson("/api/unidades/{$unidade->id}/ldap-config/testar", [
                'host' => 'x', 'port' => 636, 'base_dn' => 'x', 'username' => 'x', 'password' => 'x',
            ])
            ->assertForbidden();
    }
}
