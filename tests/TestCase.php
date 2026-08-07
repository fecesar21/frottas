<?php

namespace Tests;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    // RefreshDatabase reseta o banco entre testes, mas não o cache de
    // arquivo (driver `file`, ver config/cache.php). Sem isto, relatórios
    // cacheados (dashboard/eficiencia) vazariam dados de um teste para o
    // próximo, mesmo após o banco ser resetado.
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    protected function loginAs(string $perfil = 'admin'): Usuario
    {
        // Create new user with specified profile
        $usuario = Usuario::factory()->create(['perfil' => $perfil]);
        $token = $usuario->createToken('test')->plainTextToken;

        // Explicitly reset ALL default headers - this is critical to clear previous tokens
        $this->defaultHeaders = [];

        // Set the new authorization token
        $this->withToken($token);

        return $usuario;
    }

    protected function loginAdmin(): Usuario
    {
        return $this->loginAs('admin');
    }

    protected function loginGestor(): Usuario
    {
        return $this->loginAs('gestor');
    }

    protected function loginOperador(): Usuario
    {
        return $this->loginAs('operador');
    }
}
