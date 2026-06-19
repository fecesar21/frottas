<?php

namespace Tests;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function loginAs(string $perfil = 'admin'): Usuario
    {
        $usuario = Usuario::factory()->create(['perfil' => $perfil]);
        $this->actingAs($usuario, 'sanctum');

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
