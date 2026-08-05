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

    public function test_comprovante_svg_e_rejeitado(): void
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
            'comprovante' => UploadedFile::fake()->create('malicioso.svg', 10, 'image/svg+xml'),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('comprovante');
    }

    public function test_operador_nao_visualiza_abastecimentos_de_outro_motorista(): void
    {
        $usuario = $this->loginOperador();
        $veiculo = Veiculo::factory()->create();
        $meuMotorista = Motorista::factory()->create();
        $outroMotorista = Motorista::factory()->create();
        $usuario->update(['motorista_id' => $meuMotorista->id]);

        $meuAbastecimento = Abastecimento::create([
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $meuMotorista->id,
            'combustivel' => 'diesel_s10',
            'litros' => 50,
            'valor_litro' => 5.50,
            'km_momento' => 1000,
            'abastecido_at' => now(),
        ]);

        $abastecimentoDeOutro = Abastecimento::create([
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $outroMotorista->id,
            'combustivel' => 'diesel_s10',
            'litros' => 30,
            'valor_litro' => 5.50,
            'km_momento' => 1500,
            'abastecido_at' => now(),
        ]);

        $response = $this->getJson('/api/abastecimentos')->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($meuAbastecimento->id));
        $this->assertFalse($ids->contains($abastecimentoDeOutro->id));
    }

    public function test_operador_nao_visualiza_detalhe_de_abastecimento_de_outro_motorista(): void
    {
        $usuario = $this->loginOperador();
        $veiculo = Veiculo::factory()->create();
        $meuMotorista = Motorista::factory()->create();
        $outroMotorista = Motorista::factory()->create();
        $usuario->update(['motorista_id' => $meuMotorista->id]);

        $abastecimentoDeOutro = Abastecimento::create([
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $outroMotorista->id,
            'combustivel' => 'diesel_s10',
            'litros' => 30,
            'valor_litro' => 5.50,
            'km_momento' => 1500,
            'abastecido_at' => now(),
        ]);

        $this->getJson("/api/abastecimentos/{$abastecimentoDeOutro->id}")->assertForbidden();
    }

    public function test_resumo_exclui_abastecimentos_soft_deletados(): void
    {
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create();
        $motorista = Motorista::factory()->create();

        $mantido = Abastecimento::create([
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'combustivel' => 'diesel_s10',
            'litros' => 50,
            'valor_litro' => 5.50,
            'km_momento' => 1000,
            'abastecido_at' => now(),
        ]);

        $removido = Abastecimento::create([
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'combustivel' => 'diesel_s10',
            'litros' => 30,
            'valor_litro' => 5.50,
            'km_momento' => 1500,
            'abastecido_at' => now(),
        ]);
        $removido->delete();

        $resumo = $this->getJson('/api/abastecimentos/resumo')->assertOk()->json();
        $linha = collect($resumo)->firstWhere('veiculo_id', $veiculo->id);

        $this->assertNotNull($linha);
        $this->assertSame(1, (int) $linha['total_abastecimentos']);
        $this->assertEquals(50.0, (float) $linha['total_litros']);
    }
}
