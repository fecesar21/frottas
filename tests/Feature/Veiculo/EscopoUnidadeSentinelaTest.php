<?php

namespace Tests\Feature\Veiculo;

use App\Models\Veiculo;
use Tests\TestCase;

class EscopoUnidadeSentinelaTest extends TestCase
{
    public function test_usuario_nao_admin_sem_unidade_mapeada_nao_ve_veiculos_de_outras_unidades(): void
    {
        // Usuário sem unidade_id (não mapeado) não deve herdar o comportamento
        // "sem filtro" do admin — deve ver zero resultados até ser mapeado.
        $this->loginAs('gestor');
        Veiculo::factory()->count(3)->create();

        $response = $this->getJson('/api/veiculos')->assertOk();

        $this->assertSame(0, $response->json('meta.total') ?? count($response->json('data')));
    }
}
