<?php

namespace Tests\Feature\Checklist;

use App\Models\Checkin;
use App\Models\Veiculo;
use App\Services\ChecklistVeiculoService;
use Carbon\Carbon;
use Database\Seeders\ChecklistVeiculoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistPlantaoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enviarChecklistEm(string $momento): Veiculo
    {
        Carbon::setTestNow($momento);
        $this->seed(ChecklistVeiculoSeeder::class);
        $veiculo = Veiculo::factory()->create();
        $checkin = Checkin::factory()->create(['veiculo_id' => $veiculo->id]);

        $checklist = app(ChecklistVeiculoService::class)->iniciarOuObter($checkin);
        $checklist->respostas()->update(['conforme' => true]);
        $checklist->update(['status' => 'enviado', 'enviado_at' => now()]);

        return $veiculo;
    }

    private function exigeChecklistEm(Veiculo $veiculo, string $momento): bool
    {
        Carbon::setTestNow($momento);

        return app(ChecklistVeiculoService::class)->necessitaChecklist($veiculo->id);
    }

    public function test_plantao_noturno_nao_exige_novo_checklist_apos_meia_noite(): void
    {
        $veiculo = $this->enviarChecklistEm('2026-10-01 20:00:00');

        $this->assertFalse($this->exigeChecklistEm($veiculo, '2026-10-02 00:30:00'));
        $this->assertFalse($this->exigeChecklistEm($veiculo, '2026-10-02 06:59:00'));
        $this->assertTrue($this->exigeChecklistEm($veiculo, '2026-10-02 07:00:00'));
    }

    public function test_plantao_diurno_exige_novo_checklist_as_19h(): void
    {
        $veiculo = $this->enviarChecklistEm('2026-10-01 08:00:00');

        $this->assertFalse($this->exigeChecklistEm($veiculo, '2026-10-01 18:59:00'));
        $this->assertTrue($this->exigeChecklistEm($veiculo, '2026-10-01 19:00:00'));
    }

    public function test_relacao_checklist_hoje_segue_o_plantao(): void
    {
        $veiculo = $this->enviarChecklistEm('2026-10-01 22:00:00');

        Carbon::setTestNow('2026-10-02 03:00:00');
        $this->assertNotNull($veiculo->fresh()->checklistHoje);

        Carbon::setTestNow('2026-10-02 08:00:00');
        $this->assertNull($veiculo->fresh()->checklistHoje);
    }
}
