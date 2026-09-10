<?php

namespace Tests\Unit\Models;

use App\Models\Localidade;
use Tests\TestCase;

class LocalidadeTest extends TestCase
{
    public function test_cria_localidade_com_uuid_e_ativo_por_padrao(): void
    {
        $localidade = Localidade::create([
            'nome' => 'Clínica Parceira ABC',
            'endereco' => 'Rua das Flores, 100',
            'latitude' => -23.5505,
            'longitude' => -46.6333,
            'telefone' => '(11) 4002-8922',
            'email' => 'contato@clinicaabc.com.br',
        ]);

        $this->assertTrue((bool) preg_match('/^[0-9a-f-]{36}$/', $localidade->id));
        $this->assertTrue($localidade->ativo);
        $this->assertSame('Clínica Parceira ABC', $localidade->fresh()->nome);
    }
}
