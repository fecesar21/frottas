<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
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
}
