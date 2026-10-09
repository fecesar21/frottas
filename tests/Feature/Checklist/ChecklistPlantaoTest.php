<?php

namespace Tests\Feature\Checklist;

use App\Models\Checkin;
use App\Models\Veiculo;
use App\Services\ChecklistVeiculoService;
use App\Support\Plantao;
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
        $checkin = Checkin::factory()->create([
            'veiculo_id' => $veiculo->id,
            'turno' => Plantao::atual()['turno'] === 'diurno' ? 'dia' : 'noite',
            'checkin_at' => now(),
        ]);

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

    private function checkinComChecklistEnviado(string $momento, string $turno, ?Veiculo $veiculo = null): Checkin
    {
        Carbon::setTestNow($momento);
        if (! $veiculo) {
            $this->seed(ChecklistVeiculoSeeder::class);
            $veiculo = Veiculo::factory()->create();
        }
        $checkin = Checkin::factory()->create([
            'veiculo_id' => $veiculo->id,
            'turno' => $turno,
            'checkin_at' => now(),
        ]);

        $checklist = app(ChecklistVeiculoService::class)->iniciarOuObter($checkin);
        $checklist->respostas()->update(['conforme' => true]);
        $checklist->update(['status' => 'enviado', 'enviado_at' => now()]);

        return $checkin;
    }

    private function checkinExigeChecklistEm(Checkin $checkin, string $momento): bool
    {
        Carbon::setTestNow($momento);

        return app(ChecklistVeiculoService::class)->necessitaChecklist($checkin->veiculo_id, $checkin->fresh());
    }

    public function test_checkin_diurno_antecipado_nao_exige_novo_checklist_as_7h(): void
    {
        $checkin = $this->checkinComChecklistEnviado('2026-10-01 06:50:00', 'dia');

        $this->assertFalse($this->checkinExigeChecklistEm($checkin, '2026-10-01 07:05:00'));
        $this->assertDatabaseHas('checklists_veiculo', ['checkin_id' => $checkin->id, 'turno' => 'diurno']);
    }

    public function test_checkin_diurno_nao_exige_checklist_apos_19h(): void
    {
        $checkin = $this->checkinComChecklistEnviado('2026-10-01 07:00:00', 'dia');

        $this->assertFalse($this->checkinExigeChecklistEm($checkin, '2026-10-01 19:20:00'));
    }

    public function test_checkin_noturno_antecipado_vale_ate_a_manha_seguinte(): void
    {
        $checkin = $this->checkinComChecklistEnviado('2026-10-01 18:50:00', 'noite');

        $this->assertFalse($this->checkinExigeChecklistEm($checkin, '2026-10-01 19:05:00'));
        $this->assertFalse($this->checkinExigeChecklistEm($checkin, '2026-10-02 07:20:00'));
        $this->assertDatabaseHas('checklists_veiculo', ['checkin_id' => $checkin->id, 'turno' => 'noturno']);
    }

    public function test_novo_checkin_do_plantao_seguinte_exige_novo_checklist(): void
    {
        $diurno = $this->checkinComChecklistEnviado('2026-10-01 07:00:00', 'dia');

        Carbon::setTestNow('2026-10-01 18:55:00');
        $noturno = Checkin::factory()->create([
            'veiculo_id' => $diurno->veiculo_id,
            'turno' => 'noite',
            'checkin_at' => now(),
        ]);

        $this->assertTrue(app(ChecklistVeiculoService::class)->necessitaChecklist($noturno->veiculo_id, $noturno));
    }
}
