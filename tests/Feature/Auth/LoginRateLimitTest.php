<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DirectoryEmulator::setup('default');
    }

    protected function tearDown(): void
    {
        DirectoryEmulator::tearDown();
        parent::tearDown();
    }

    private function criarUsuarioLdap(string $samaccountname): void
    {
        LdapUser::create([
            'cn' => $samaccountname,
            'displayname' => $samaccountname,
            'samaccountname' => $samaccountname,
            'mail' => $samaccountname.'@empresa.com.br',
            'objectguid' => Str::uuid()->toString(),
        ]);
    }

    public function test_sexta_tentativa_de_login_no_mesmo_minuto_retorna_429(): void
    {
        Usuario::factory()->create([
            'cpf' => '55555555555',
            'senha_hash' => Hash::make('senha123'),
            'ativo' => true,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'usuario' => '55555555555',
                'senha' => 'errada',
            ]);

            $response->assertUnauthorized();
        }

        // 6ª tentativa dentro do mesmo minuto deve ser bloqueada pelo limiter `login`.
        $this->postJson('/api/auth/login', [
            'usuario' => '55555555555',
            'senha' => 'errada',
        ])->assertStatus(429);
    }

    public function test_login_ad_e_throttled_por_usuario_nao_apenas_por_ip(): void
    {
        $this->criarUsuarioLdap('usuarioA');
        $this->criarUsuarioLdap('usuarioB');

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/auth/login-ad', [
                'usuario' => 'usuarioA',
                'senha' => 'errada',
            ])->assertUnauthorized();
        }

        // 6ª tentativa do mesmo usuário no mesmo minuto: bloqueada.
        $this->postJson('/api/auth/login-ad', [
            'usuario' => 'usuarioA',
            'senha' => 'errada',
        ])->assertStatus(429);

        // Outro usuário, mesmo IP: não deve ser afetado pelo throttle do usuarioA.
        $this->postJson('/api/auth/login-ad', [
            'usuario' => 'usuarioB',
            'senha' => 'errada',
        ])->assertUnauthorized();
    }
}
