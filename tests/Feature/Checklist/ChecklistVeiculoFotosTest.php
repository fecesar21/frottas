<?php

namespace Tests\Feature\Checklist;

use App\Models\Checkin;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Services\ChecklistVeiculoService;
use Database\Seeders\ChecklistVeiculoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChecklistVeiculoFotosTest extends TestCase
{
    use RefreshDatabase;

    private function enviarItem(int $qtdFotos)
    {
        Storage::fake('public');
        $this->seed(ChecklistVeiculoSeeder::class);
        $veiculo = Veiculo::factory()->create(['modelo' => 'FIORINO']);
        $checkin = Checkin::factory()->create(['veiculo_id' => $veiculo->id]);
        $checklist = app(ChecklistVeiculoService::class)->iniciarOuObter($checkin);
        $resposta = $checklist->respostas->first(fn ($r) => ! $r->itemModelo->requer_valor);

        $fotos = collect(range(1, $qtdFotos))->map(fn ($i) => UploadedFile::fake()->image("foto{$i}.jpg"))->all();

        $this->actingAs(Usuario::factory()->create(['perfil' => 'admin']));

        return [$resposta, $this->post("/api/checklist-veiculo/{$checklist->id}/item", [
            '_method' => 'PATCH',
            'item_modelo_id' => $resposta->item_modelo_id,
            'conforme' => 0,
            'observacao' => 'Pneu careca',
            'fotos' => $fotos,
        ], ['Accept' => 'application/json'])];
    }

    public function test_aceita_ate_tres_fotos_por_nao_conformidade(): void
    {
        [$resposta, $response] = $this->enviarItem(3);

        $response->assertOk();
        $fotos = $resposta->fresh()->fotos;
        $this->assertCount(3, $fotos);
        foreach ($fotos as $path) {
            Storage::disk('public')->assertExists($path);
        }
        $this->assertSame($fotos[0], $resposta->fresh()->foto_path);
    }

    public function test_recusa_mais_de_tres_fotos(): void
    {
        [, $response] = $this->enviarItem(4);

        $response->assertStatus(422)->assertJsonValidationErrors('fotos');
    }
}
