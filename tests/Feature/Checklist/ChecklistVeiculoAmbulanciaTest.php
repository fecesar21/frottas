<?php

namespace Tests\Feature\Checklist;

use App\Models\Checkin;
use App\Models\Veiculo;
use App\Services\ChecklistVeiculoService;
use Database\Seeders\ChecklistVeiculoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistVeiculoAmbulanciaTest extends TestCase
{
    use RefreshDatabase;

    private function checklistPara(string $modelo)
    {
        $this->seed(ChecklistVeiculoSeeder::class);
        $veiculo = Veiculo::factory()->create(['modelo' => $modelo]);
        $checkin = Checkin::factory()->create(['veiculo_id' => $veiculo->id]);

        return app(ChecklistVeiculoService::class)->iniciarOuObter($checkin);
    }

    public function test_carro_administrativo_nao_recebe_item_de_oxigenio(): void
    {
        $labels = $this->checklistPara('FIORINO')->respostas->pluck('itemModelo.label');

        $this->assertCount(12, $labels);
        $this->assertNotContains('Nível de Oxigênio', $labels);
    }

    public function test_ambulancia_recebe_item_de_oxigenio(): void
    {
        $labels = $this->checklistPara('Ambulância UPA')->respostas->pluck('itemModelo.label');

        $this->assertCount(13, $labels);
        $this->assertContains('Nível de Oxigênio', $labels);
    }
}
