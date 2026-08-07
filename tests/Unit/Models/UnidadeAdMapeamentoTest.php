<?php

// tests/Unit/Models/UnidadeAdMapeamentoTest.php

namespace Tests\Unit\Models;

use App\Models\Unidade;
use App\Models\UnidadeAdMapeamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnidadeAdMapeamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_unidade_pelo_valor_ad(): void
    {
        $unidade = Unidade::factory()->create();

        UnidadeAdMapeamento::create([
            'valor_ad' => 'HOSP-CENTRO',
            'unidade_id' => $unidade->id,
        ]);

        $mapeamento = UnidadeAdMapeamento::where('valor_ad', 'HOSP-CENTRO')->first();

        $this->assertNotNull($mapeamento);
        $this->assertSame($unidade->id, $mapeamento->unidade_id);
        $this->assertTrue($mapeamento->unidade->is($unidade));
    }
}
