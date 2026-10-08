<?php

namespace Tests\Feature\MotivoViagem;

use App\Models\MotivoViagem;
use App\Models\Usuario;
use App\Models\Viagem;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MotivoViagemApiTest extends TestCase
{
    public function test_lista_ativos_para_qualquer_usuario_e_todos_so_para_admin(): void
    {
        MotivoViagem::porCodigo('tfd')->update(['ativo' => false]);
        Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'operador']));
        $this->getJson('/api/motivos-viagem')->assertOk()->assertJsonCount(7, 'data');
        $this->getJson('/api/motivos-viagem?solicitacao=1')->assertJsonCount(5, 'data');
        $this->getJson('/api/motivos-viagem?todos=1')->assertJsonCount(7, 'data'); // ignorado p/ não-admin

        Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'admin']));
        $this->getJson('/api/motivos-viagem?todos=1')->assertJsonCount(8, 'data')
            ->assertJsonPath('data.0.em_uso', false);
    }

    public function test_solicitante_pode_listar_motivos(): void
    {
        Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'solicitante']));
        $this->getJson('/api/motivos-viagem?solicitacao=1')->assertOk();
    }

    public function test_operador_nao_altera(): void
    {
        Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'operador']));
        $this->postJson('/api/motivos-viagem', ['nome' => 'X', 'tipo_veiculo' => 'ambos'])->assertForbidden();
    }

    public function test_admin_cria_com_codigo_gerado_e_sem_colisao(): void
    {
        Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'admin']));
        $this->postJson('/api/motivos-viagem', ['nome' => 'Alimentação (Levar/Buscar)', 'tipo_veiculo' => 'ambos'])
            ->assertJsonValidationErrors(['nome']); // nome duplicado
        $this->postJson('/api/motivos-viagem', ['nome' => 'Alimentacao', 'tipo_veiculo' => 'ambos', 'disponivel_solicitacao' => true])
            ->assertCreated()->assertJsonPath('data.codigo', 'alimentacao_2')->assertJsonPath('data.sistema', false);
    }

    public function test_sistema_nao_muda_tipo_nem_exclui_mas_renomeia_e_inativa(): void
    {
        Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'admin']));
        $tfd = MotivoViagem::porCodigo('tfd');
        $this->putJson("/api/motivos-viagem/{$tfd->id}", ['tipo_veiculo' => 'ambos'])->assertJsonValidationErrors(['tipo_veiculo']);
        $this->putJson("/api/motivos-viagem/{$tfd->id}", ['nome' => 'TFD (Fora do Domicílio)', 'ativo' => false])->assertOk();
        $this->deleteJson("/api/motivos-viagem/{$tfd->id}")->assertStatus(422);
    }

    public function test_codigo_e_imutavel_na_edicao(): void
    {
        Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'admin']));
        $motivo = MotivoViagem::factory()->create();
        $codigo = $motivo->codigo;
        $this->putJson("/api/motivos-viagem/{$motivo->id}", ['nome' => 'Outro nome qualquer', 'codigo' => 'hackeado'])
            ->assertOk()->assertJsonPath('data.codigo', $codigo);
        $this->assertSame($codigo, $motivo->fresh()->codigo);
    }

    public function test_exclui_so_quando_nao_usado(): void
    {
        Sanctum::actingAs(Usuario::factory()->create(['perfil' => 'admin']));
        $usado = MotivoViagem::factory()->create();
        $livre = MotivoViagem::factory()->create();
        Viagem::factory()->create(['motivo_viagem' => $usado->codigo]);
        $this->deleteJson("/api/motivos-viagem/{$usado->id}")->assertStatus(422);
        $this->deleteJson("/api/motivos-viagem/{$livre->id}")->assertOk();
        $this->assertModelMissing($livre);
    }
}
