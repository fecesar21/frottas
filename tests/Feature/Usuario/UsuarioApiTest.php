<?php

namespace Tests\Feature\Usuario;

use App\Models\Usuario;
use Tests\TestCase;

class UsuarioApiTest extends TestCase
{
    public function test_admin_lista_usuarios(): void
    {
        $this->loginAdmin();
        Usuario::factory()->count(2)->create();

        $this->getJson('/api/usuarios')->assertOk();
    }

    public function test_gestor_nao_acessa_usuarios(): void
    {
        $this->loginGestor();

        $this->getJson('/api/usuarios')->assertForbidden();
    }

    public function test_admin_cria_usuario(): void
    {
        $this->loginAdmin();

        $response = $this->postJson('/api/usuarios', [
            'nome' => 'Novo Usuário',
            'cpf' => '12345678900',
            'senha' => '123456',
            'perfil' => 'gestor',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('usuarios', ['cpf' => '12345678900', 'perfil' => 'gestor']);
    }

    public function test_admin_nao_pode_desativar_a_si_mesmo(): void
    {
        $admin = $this->loginAdmin();

        $this->deleteJson("/api/usuarios/{$admin->id}")
            ->assertStatus(422)
            ->assertJsonPath('error', 'Não é possível desativar o próprio usuário');
    }

    public function test_admin_desativa_outro_usuario(): void
    {
        $this->loginAdmin();
        $outro = Usuario::factory()->create(['ativo' => true]);

        $this->deleteJson("/api/usuarios/{$outro->id}")->assertOk();
        $this->assertDatabaseHas('usuarios', ['id' => $outro->id, 'ativo' => false]);
    }
}
