<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_login_com_credenciais_validas(): void
    {
        Usuario::factory()->create([
            'cpf'        => '11111111111',
            'senha_hash' => Hash::make('senha123'),
            'ativo'      => true,
        ]);

        $this->postJson('/api/auth/login', [
            'usuario' => '11111111111',
            'senha'   => 'senha123',
        ])->assertOk()
          ->assertJsonStructure(['token', 'user' => ['id', 'nome', 'email', 'perfil']]);
    }

    public function test_login_com_cpf_formatado(): void
    {
        Usuario::factory()->create([
            'cpf'        => '22222222222',
            'senha_hash' => Hash::make('senha123'),
            'ativo'      => true,
        ]);

        // Backend deve normalizar a máscara antes de buscar
        $this->postJson('/api/auth/login', [
            'usuario' => '222.222.222-22',
            'senha'   => 'senha123',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_login_com_senha_errada_retorna_401(): void
    {
        Usuario::factory()->create([
            'cpf'        => '33333333333',
            'senha_hash' => Hash::make('senha123'),
        ]);

        $this->postJson('/api/auth/login', [
            'usuario' => '33333333333',
            'senha'   => 'errada',
        ])->assertUnauthorized();
    }

    public function test_usuario_inativo_nao_consegue_login(): void
    {
        Usuario::factory()->create([
            'cpf'        => '44444444444',
            'senha_hash' => Hash::make('senha123'),
            'ativo'      => false,
        ]);

        $this->postJson('/api/auth/login', [
            'usuario' => '44444444444',
            'senha'   => 'senha123',
        ])->assertUnauthorized();
    }

    public function test_me_retorna_usuario_autenticado(): void
    {
        $usuario = $this->loginAdmin();

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $usuario->id);
    }

    public function test_logout_invalida_token(): void
    {
        $this->loginAdmin();

        $this->postJson('/api/auth/logout')->assertOk();
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_rota_protegida_sem_token_retorna_401(): void
    {
        $this->getJson('/api/veiculos')->assertUnauthorized();
    }
}
