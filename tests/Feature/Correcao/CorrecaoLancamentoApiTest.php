<?php

namespace Tests\Feature\Correcao;

use App\Models\Abastecimento;
use App\Models\AuditoriaCorrecao;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Models\Viagem;
use Tests\TestCase;

class CorrecaoLancamentoApiTest extends TestCase
{
    private function viagem(array $attrs = []): Viagem
    {
        return Viagem::create(array_merge([
            'veiculo_id' => Veiculo::factory()->create()->id,
            'motorista_id' => Motorista::factory()->create()->id,
            'origem' => 'A',
            'destino' => 'B',
            'motivo_viagem' => 'buscar_medico',
            'km_saida' => 1000,
            'km_chegada' => 1050,
            'saida_at' => now()->subHour(),
            'chegada_at' => now(),
            'status' => 'concluida',
        ], $attrs));
    }

    private function abastecimento(): Abastecimento
    {
        return Abastecimento::create([
            'veiculo_id' => Veiculo::factory()->create()->id,
            'motorista_id' => Motorista::factory()->create()->id,
            'combustivel' => 'diesel_s10',
            'litros' => 50,
            'valor_litro' => 5.50,
            'km_momento' => 1000,
            'abastecido_at' => now(),
        ]);
    }

    public function test_admin_corrige_km_da_viagem(): void
    {
        $this->loginAdmin();
        $viagem = $this->viagem();

        $this->patchJson("/api/viagens/{$viagem->id}/correcao", ['km_saida' => 1010, 'km_chegada' => 1080])
            ->assertOk()
            ->assertJsonPath('data.km_percorrido', 70);

        $this->assertDatabaseHas('viagens', ['id' => $viagem->id, 'km_saida' => 1010, 'km_chegada' => 1080]);
    }

    public function test_correcao_rejeita_chegada_menor_que_saida(): void
    {
        $this->loginAdmin();
        $viagem = $this->viagem();

        $this->patchJson("/api/viagens/{$viagem->id}/correcao", ['km_saida' => 1100, 'km_chegada' => 1050])
            ->assertStatus(422);
    }

    public function test_viagem_em_andamento_so_corrige_km_saida(): void
    {
        $this->loginAdmin();
        $viagem = $this->viagem(['status' => 'em_andamento', 'km_chegada' => null, 'chegada_at' => null]);

        $this->patchJson("/api/viagens/{$viagem->id}/correcao", ['km_saida' => 990, 'km_chegada' => 2000])->assertOk();

        $this->assertDatabaseHas('viagens', ['id' => $viagem->id, 'km_saida' => 990, 'km_chegada' => null]);
    }

    public function test_admin_corrige_abastecimento(): void
    {
        $this->loginAdmin();
        $a = $this->abastecimento();

        $this->patchJson("/api/abastecimentos/{$a->id}", ['litros' => 40, 'valor_litro' => 6, 'km_momento' => 1200])
            ->assertOk()
            ->assertJsonPath('data.valor_total', 240);
    }

    public function test_nao_admin_nao_corrige(): void
    {
        $viagem = $this->viagem();
        $a = $this->abastecimento();

        foreach (['loginGestor', 'loginOperador'] as $login) {
            $this->$login();
            $this->patchJson("/api/viagens/{$viagem->id}/correcao", ['km_saida' => 1, 'km_chegada' => 2])->assertForbidden();
            $this->putJson("/api/viagens/{$viagem->id}", ['km_saida' => 1])->assertForbidden();
            $this->patchJson("/api/abastecimentos/{$a->id}", ['litros' => 1, 'valor_litro' => 1, 'km_momento' => 1])->assertForbidden();
            $this->deleteJson("/api/abastecimentos/{$a->id}")->assertForbidden();
        }
    }

    public function test_correcao_de_viagem_gera_auditoria_so_dos_campos_alterados(): void
    {
        $admin = $this->loginAdmin();
        $viagem = $this->viagem();

        $this->patchJson("/api/viagens/{$viagem->id}/correcao", ['km_saida' => 1000, 'km_chegada' => 1080])->assertOk();

        $log = AuditoriaCorrecao::sole();
        $this->assertSame($admin->id, $log->usuario_id);
        $this->assertSame('viagem', $log->entidade);
        $this->assertSame($viagem->id, $log->entidade_id);
        $this->assertSame('correcao', $log->acao);
        $this->assertSame(['km_chegada' => 1050], $log->antes);
        $this->assertSame(['km_chegada' => 1080], $log->depois);
    }

    public function test_correcao_sem_mudanca_nao_gera_auditoria(): void
    {
        $this->loginAdmin();
        $viagem = $this->viagem();

        $this->patchJson("/api/viagens/{$viagem->id}/correcao", ['km_saida' => 1000, 'km_chegada' => 1050])->assertOk();

        $this->assertSame(0, AuditoriaCorrecao::count());
    }

    public function test_correcao_e_exclusao_de_abastecimento_geram_auditoria(): void
    {
        $this->loginAdmin();
        $a = $this->abastecimento();

        $this->patchJson("/api/abastecimentos/{$a->id}", ['litros' => 40, 'valor_litro' => 5.50, 'km_momento' => 1000])->assertOk();
        $this->deleteJson("/api/abastecimentos/{$a->id}")->assertOk();

        $this->getJson("/api/auditoria-correcoes?entidade=abastecimento&entidade_id={$a->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.acao', 'correcao')
            ->assertJsonPath('data.1.depois', ['litros' => 40])
            ->assertJsonPath('data.0.acao', 'exclusao');
    }

    public function test_nao_admin_nao_consulta_auditoria(): void
    {
        $this->loginGestor();
        $this->getJson('/api/auditoria-correcoes')->assertForbidden();
    }
}
