<?php

namespace Tests\Feature\Escala;

use App\Models\Escala;
use App\Models\Motorista;
use Tests\TestCase;

class EscalaApiTest extends TestCase
{
    public function test_remover_escala_e_soft_delete(): void
    {
        $this->loginGestor();
        $motorista = Motorista::factory()->create();
        $escala = Escala::create([
            'motorista_id' => $motorista->id,
            'data' => now()->toDateString(),
            'turno' => 'dia',
        ]);

        $this->deleteJson("/api/escalas/{$escala->id}")->assertOk();

        $this->assertSoftDeleted('escalas', ['id' => $escala->id]);
    }
}
