<?php

namespace Tests\Feature\Abastecimento;

use App\Models\Abastecimento;
use App\Models\Motorista;
use App\Models\Veiculo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AbastecimentoApiTest extends TestCase
{
    public function test_remover_abastecimento_e_soft_delete(): void
    {
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create();
        $motorista = Motorista::factory()->create();
        $abastecimento = Abastecimento::create([
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'combustivel' => 'diesel_s10',
            'litros' => 50,
            'valor_litro' => 5.50,
            'km_momento' => 1000,
            'abastecido_at' => now(),
        ]);

        $this->deleteJson("/api/abastecimentos/{$abastecimento->id}")->assertOk();

        $this->assertSoftDeleted('abastecimentos', ['id' => $abastecimento->id]);
    }

    public function test_cria_abastecimento_com_comprovante(): void
    {
        Storage::fake('public');
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create();
        $motorista = Motorista::factory()->create();

        $response = $this->postJson('/api/abastecimentos', [
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'combustivel' => 'diesel_s10',
            'litros' => 40,
            'valor_litro' => 5.80,
            'km_momento' => 2000,
            'comprovante' => UploadedFile::fake()->image('nota.jpg'),
        ]);

        $response->assertCreated();
        $this->assertNotNull($response->json('data.comprovante_url'));

        $abastecimento = Abastecimento::first();
        Storage::disk('public')->assertExists($abastecimento->comprovante_path);
    }
}
