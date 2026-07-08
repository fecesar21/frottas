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

    public function test_store_restaura_escala_soft_deletada_em_vez_de_colidir(): void
    {
        $this->loginGestor();
        $motorista = Motorista::factory()->create();
        $data = now()->toDateString();
        $escala = Escala::create([
            'motorista_id' => $motorista->id,
            'data' => $data,
            'turno' => 'dia',
        ]);
        $escala->delete();
        $this->assertSoftDeleted('escalas', ['id' => $escala->id]);

        $response = $this->postJson('/api/escalas', [
            'motorista_id' => $motorista->id,
            'data' => $data,
            'turno' => 'noite',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('escalas', [
            'id' => $escala->id,
            'deleted_at' => null,
            'turno' => 'noite',
        ]);
    }
}
