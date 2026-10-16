<?php

namespace Tests\Feature\MotivoViagem;

use App\Models\Colaborador;
use App\Models\MotivoViagem;
use App\Models\Unidade;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MotivoViagemDepartamentoUpaTest extends TestCase
{
    private function solicitante(string $unidadeNome, ?string $departamento): Usuario
    {
        $unidade = Unidade::factory()->create(['nome' => $unidadeNome]);
        $usuario = Usuario::factory()->create([
            'perfil' => 'solicitante', 'unidade_id' => $unidade->id, 'ldap_guid' => (string) str()->uuid(),
        ]);
        if ($departamento !== null) {
            Colaborador::factory()->create([
                'unidade_id' => $unidade->id, 'ldap_guid' => $usuario->ldap_guid, 'departamento' => $departamento,
            ]);
        }

        return $usuario;
    }

    private function exames(): MotivoViagem
    {
        return MotivoViagem::create([
            'codigo' => 'exames_levar_paciente', 'nome' => 'Exames - Levar Paciente',
            'tipo_veiculo' => 'ambos', 'disponivel_solicitacao' => true,
        ]);
    }

    private function codigos(): array
    {
        return collect($this->getJson('/api/motivos-viagem?solicitacao=1')->assertOk()->json('data'))
            ->pluck('codigo')->all();
    }

    public function test_enfermagem_e_pronto_atendimento_da_upa_veem_so_transferencia_e_exames(): void
    {
        $this->exames();
        foreach (['ENFERMAGEM', 'Pronto Atendimento'] as $dep) {
            Sanctum::actingAs($this->solicitante("UPA {$dep}", $dep));
            $this->assertEqualsCanonicalizing(['transferencia_paciente', 'exames_levar_paciente'], $this->codigos());
        }
    }

    public function test_outra_uo_da_upa_ve_todas_menos_transferencia_e_exames(): void
    {
        $this->exames();
        Sanctum::actingAs($this->solicitante('UPA', 'FARMÁCIA'));
        $codigos = $this->codigos();
        $this->assertNotEmpty($codigos);
        $this->assertNotContains('transferencia_paciente', $codigos);
        $this->assertNotContains('exames_levar_paciente', $codigos);
    }

    public function test_sem_departamento_ou_fora_da_upa_ve_todas(): void
    {
        $this->exames();
        Sanctum::actingAs($this->solicitante('UPA', null));
        $this->assertContains('transferencia_paciente', $this->codigos());
        $this->assertContains('buscar_medico', $this->codigos());

        Sanctum::actingAs($this->solicitante('HOSPITAL CENTRAL', 'ENFERMAGEM'));
        $this->assertContains('transferencia_paciente', $this->codigos());
        $this->assertContains('buscar_medico', $this->codigos());
    }

    public function test_criacao_recusa_motivo_fora_do_permitido(): void
    {
        Sanctum::actingAs($this->solicitante('UPA', 'FARMÁCIA'));
        $this->postJson('/api/solicitacoes', ['motivo' => 'transferencia_paciente'])
            ->assertJsonValidationErrors(['motivo' => 'não está disponível']);

        Sanctum::actingAs($this->solicitante('UPA 2', 'ENFERMAGEM'));
        $this->postJson('/api/solicitacoes', ['motivo' => 'buscar_medico'])
            ->assertJsonValidationErrors(['motivo' => 'não está disponível']);
    }
}
