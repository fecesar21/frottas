<?php

namespace Tests\Feature\Viagem;

use App\Models\Checkin;
use App\Models\ChecklistVeiculo;
use App\Models\Colaborador;
use App\Models\MotivoViagem;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Support\Plantao;
use Tests\TestCase;

class ViagemApiTest extends TestCase
{
    private function liberarChecklist(Veiculo $veiculo, Motorista $motorista, ?Checkin $checkin = null): void
    {
        ChecklistVeiculo::create([
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'checkin_id' => ($checkin ?? Checkin::factory()->create([
                'veiculo_id' => $veiculo->id,
                'motorista_id' => $motorista->id,
            ]))->id,
            'data_referencia' => Plantao::atual()['data'],
            'turno' => Plantao::atual()['turno'],
            'status' => 'enviado',
            'enviado_at' => now(),
        ]);
    }

    public function test_lista_viagens(): void
    {
        $this->loginAdmin();
        Viagem::factory()->count(3)->create();

        $this->getJson('/api/viagens')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'origem', 'destino', 'status']]]);
    }

    public function test_cria_viagem(): void
    {
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create(['km_atual' => 4000, 'modelo' => 'STRADA']);
        $motorista = Motorista::factory()->create();
        $this->liberarChecklist($veiculo, $motorista);

        $response = $this->postJson('/api/viagens', [
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'origem' => 'São Paulo',
            'destino' => 'Campinas',
            'km_saida' => 5000,
            'motivo_viagem' => 'buscar_medico',
        ]);

        $response->assertCreated()->assertJsonPath('data.origem', 'SÃO PAULO');
        $this->assertDatabaseHas('viagens', ['origem' => 'SÃO PAULO', 'destino' => 'CAMPINAS']);
    }

    public function test_transporte_de_colaborador_exige_e_vincula_colaboradores(): void
    {
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create(['km_atual' => 4000, 'modelo' => 'STRADA']);
        $motorista = Motorista::factory()->create();
        $this->liberarChecklist($veiculo, $motorista);
        [$c1, $c2] = Colaborador::factory()->count(2)->create();
        $inativo = Colaborador::factory()->create(['ativo' => false]);

        $payload = fn (array $extra) => array_merge([
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'origem' => 'a',
            'destino' => 'b',
            'km_saida' => 5000,
            'motivo_viagem' => 'transporte_colaborador',
        ], $extra);

        $this->postJson('/api/viagens', $payload([]))->assertJsonValidationErrors(['colaborador_ids']);
        $this->postJson('/api/viagens', $payload(['colaborador_ids' => []]))->assertJsonValidationErrors(['colaborador_ids']);
        $this->postJson('/api/viagens', $payload(['colaborador_ids' => [$inativo->id]]))->assertJsonValidationErrors(['colaborador_ids.0']);

        $this->postJson('/api/viagens', $payload(['colaborador_ids' => [$c1->id, $c2->id]]))
            ->assertCreated()
            ->assertJsonCount(2, 'data.colaboradores');

        $this->assertDatabaseCount('viagem_colaborador', 2);
    }

    public function test_numero_atendimento_exige_exatamente_6_digitos_e_nao_aceita_zeros(): void
    {
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create(['km_atual' => 4000, 'modelo' => 'AMBULANCIA']);
        $motorista = Motorista::factory()->create();
        $this->liberarChecklist($veiculo, $motorista);

        $payload = fn ($numero) => [
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'origem' => 'a',
            'destino' => 'b',
            'km_saida' => 5000,
            'motivo_viagem' => 'transferencia_paciente',
            'numero_atendimento' => $numero,
        ];

        foreach ([1, 12345, '000000', 0, 1234567] as $invalido) {
            $this->postJson('/api/viagens', $payload($invalido))
                ->assertJsonValidationErrors(['numero_atendimento']);
        }

        $this->postJson('/api/viagens', $payload(123456))->assertCreated();
    }

    public function test_operador_sem_checkin_ativo_recebe_403_ao_criar_viagem(): void
    {
        $usuario = $this->loginOperador();
        $motorista = Motorista::factory()->create();
        $usuario->update(['motorista_id' => $motorista->id]);
        $veiculo = Veiculo::factory()->create();

        $this->postJson('/api/viagens', [
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'origem' => 'São Paulo',
            'destino' => 'Campinas',
            'km_saida' => 5000,
            'motivo_viagem' => 'buscar_medico',
        ])->assertForbidden();
    }

    public function test_operador_com_checkin_ativo_cria_viagem_vinculada_ao_veiculo_do_checkin(): void
    {
        $usuario = $this->loginOperador();
        $veiculo = Veiculo::factory()->create(['km_atual' => 4000, 'modelo' => 'STRADA']);
        $motorista = Motorista::factory()->create();
        $usuario->update(['motorista_id' => $motorista->id]);

        $checkin = Checkin::factory()->create([
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
            'status' => 'ativo',
        ]);
        $this->liberarChecklist($veiculo, $motorista, $checkin);

        // veiculo_id/motorista_id são exigidos pela validação da request, mas o
        // controller os sobrescreve com os dados do check-in ativo do operador.
        $response = $this->postJson('/api/viagens', [
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista->id,
            'origem' => 'São Paulo',
            'destino' => 'Campinas',
            'km_saida' => 5000,
            'motivo_viagem' => 'buscar_medico',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('viagens', [
            'motorista_id' => $motorista->id,
            'veiculo_id' => $veiculo->id,
            'checkin_id' => $checkin->id,
        ]);
    }

    public function test_operador_nao_visualiza_viagens_de_outro_motorista(): void
    {
        $usuario = $this->loginOperador();
        $meuMotorista = Motorista::factory()->create();
        $outroMotorista = Motorista::factory()->create();
        $usuario->update(['motorista_id' => $meuMotorista->id]);

        $minhaViagem = Viagem::factory()->create(['motorista_id' => $meuMotorista->id]);
        $viagemDeOutro = Viagem::factory()->create(['motorista_id' => $outroMotorista->id]);

        $response = $this->getJson('/api/viagens')->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($minhaViagem->id));
        $this->assertFalse($ids->contains($viagemDeOutro->id));
    }

    public function test_operador_nao_visualiza_detalhe_de_viagem_de_outro_motorista(): void
    {
        $usuario = $this->loginOperador();
        $meuMotorista = Motorista::factory()->create();
        $outroMotorista = Motorista::factory()->create();
        $usuario->update(['motorista_id' => $meuMotorista->id]);

        $viagemDeOutro = Viagem::factory()->create(['motorista_id' => $outroMotorista->id]);

        $this->getJson("/api/viagens/{$viagemDeOutro->id}")->assertForbidden();
    }

    public function test_detalhe_da_viagem_retorna_os_dados(): void
    {
        $this->loginAdmin();
        $viagem = Viagem::factory()->create(['motivo_viagem' => 'tfd']);

        $this->getJson("/api/viagens/{$viagem->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $viagem->id)
            ->assertJsonPath('data.motivo_nome', 'TFD');
    }

    public function test_operador_visualiza_detalhe_da_propria_viagem(): void
    {
        $usuario = $this->loginOperador();
        $motorista = Motorista::factory()->create();
        $usuario->update(['motorista_id' => $motorista->id]);
        $viagem = Viagem::factory()->create(['motorista_id' => $motorista->id]);

        $this->getJson("/api/viagens/{$viagem->id}")->assertOk()->assertJsonPath('data.id', $viagem->id);
    }

    public function test_registra_chegada_da_viagem(): void
    {
        $this->loginGestor();
        $viagem = Viagem::factory()->create(['km_saida' => 5000]);

        $this->patchJson("/api/viagens/{$viagem->id}/chegada", ['km_chegada' => 5120])
            ->assertOk()
            ->assertJsonPath('data.status', 'concluida');

        $this->assertDatabaseHas('viagens', ['id' => $viagem->id, 'km_chegada' => 5120, 'status' => 'concluida']);
    }

    public function test_motivo_respeita_tipo_do_veiculo(): void
    {
        $this->loginGestor();
        $ambulancia = Veiculo::factory()->create(['km_atual' => 4000, 'modelo' => 'AMBULÂNCIA SPRINTER']);
        $strada = Veiculo::factory()->create(['km_atual' => 4000, 'modelo' => 'STRADA']);
        $motorista = Motorista::factory()->create();
        $this->liberarChecklist($ambulancia, $motorista);
        $this->liberarChecklist($strada, $motorista);

        $payload = fn ($veiculo, $motivo) => [
            'veiculo_id' => $veiculo->id, 'motorista_id' => $motorista->id,
            'origem' => 'a', 'destino' => 'b', 'km_saida' => 5000, 'motivo_viagem' => $motivo,
        ];

        $this->postJson('/api/viagens', $payload($strada, 'tfd'))->assertJsonValidationErrors(['motivo_viagem']);
        $this->postJson('/api/viagens', $payload($ambulancia, 'alimentacao'))->assertJsonValidationErrors(['motivo_viagem']);
        $this->postJson('/api/viagens', $payload($ambulancia, 'servicos_administrativos'))->assertJsonValidationErrors(['motivo_viagem']);
        $this->postJson('/api/viagens', $payload($strada, 'alimentacao'))->assertCreated();
    }

    public function test_motivo_inativo_e_recusado_e_ambos_aceito_nos_dois_tipos(): void
    {
        $this->loginGestor();
        $ambulancia = Veiculo::factory()->create(['km_atual' => 4000, 'modelo' => 'AMBULÂNCIA SPRINTER']);
        $strada = Veiculo::factory()->create(['km_atual' => 4000, 'modelo' => 'STRADA']);
        $motorista = Motorista::factory()->create();
        $this->liberarChecklist($ambulancia, $motorista);
        $this->liberarChecklist($strada, $motorista);

        $payload = fn ($veiculo, $motivo) => [
            'veiculo_id' => $veiculo->id, 'motorista_id' => $motorista->id,
            'origem' => 'a', 'destino' => 'b', 'km_saida' => 5000, 'motivo_viagem' => $motivo,
        ];

        MotivoViagem::porCodigo('alimentacao')->update(['ativo' => false]);
        $this->postJson('/api/viagens', $payload($strada, 'alimentacao'))->assertJsonValidationErrors(['motivo_viagem']);

        $ambos = MotivoViagem::factory()->create(['tipo_veiculo' => 'ambos']);
        $this->postJson('/api/viagens', $payload($ambulancia, $ambos->codigo))->assertCreated();
    }

    public function test_editar_viagem_mantendo_motivo_inativado_continua_valido(): void
    {
        $this->loginAdmin();
        $strada = Veiculo::factory()->create(['modelo' => 'STRADA']);
        $viagem = Viagem::factory()->create(['veiculo_id' => $strada->id, 'motivo_viagem' => 'alimentacao']);
        MotivoViagem::porCodigo('alimentacao')->update(['ativo' => false]);

        $this->putJson("/api/viagens/{$viagem->id}", ['motivo_viagem' => 'alimentacao', 'origem' => 'nova'])->assertOk();
        $this->putJson("/api/viagens/{$viagem->id}", ['motivo_viagem' => 'tfd'])->assertJsonValidationErrors(['motivo_viagem']);
    }

    public function test_chegada_com_retorno_abre_viagem_invertida_com_km_de_chegada(): void
    {
        $this->loginGestor();
        $viagem = Viagem::factory()->create([
            'veiculo_id' => Veiculo::factory()->create(['km_atual' => 4000])->id,
            'km_saida' => 5000, 'origem' => 'Hospital A', 'destino' => 'Hospital B',
            'motivo_viagem' => 'transferencia_paciente', 'numero_atendimento' => 123456,
        ]);

        $res = $this->patchJson("/api/viagens/{$viagem->id}/chegada", ['km_chegada' => 5120, 'retornar_origem' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'concluida')
            ->assertJsonPath('viagem_retorno.origem', 'HOSPITAL B')
            ->assertJsonPath('viagem_retorno.destino', 'HOSPITAL A');

        $this->assertDatabaseHas('viagens', [
            'id' => $res->json('viagem_retorno.id'),
            'status' => 'em_andamento', 'km_saida' => 5120,
            'veiculo_id' => $viagem->veiculo_id, 'motorista_id' => $viagem->motorista_id,
            'motivo_viagem' => 'retorno', 'numero_atendimento' => 123456,
        ]);
    }

    public function test_chegada_sem_retorno_apenas_encerra(): void
    {
        $this->loginGestor();
        $viagem = Viagem::factory()->create(['km_saida' => 5000, 'motivo_viagem' => 'transferencia_paciente', 'numero_atendimento' => 123456]);

        $this->patchJson("/api/viagens/{$viagem->id}/chegada", ['km_chegada' => 5120, 'retornar_origem' => false])
            ->assertOk()
            ->assertJsonMissingPath('viagem_retorno');

        $this->assertSame(1, Viagem::count());
    }

    public function test_retorno_so_vale_para_transferencia_de_paciente(): void
    {
        $this->loginGestor();
        $viagem = Viagem::factory()->create(['km_saida' => 5000, 'motivo_viagem' => 'tfd']);

        $this->patchJson("/api/viagens/{$viagem->id}/chegada", ['km_chegada' => 5120, 'retornar_origem' => true])
            ->assertJsonValidationErrors(['retornar_origem']);

        $this->assertDatabaseHas('viagens', ['id' => $viagem->id, 'status' => 'em_andamento']);
    }

    public function test_viagem_de_retorno_nao_permite_novo_retorno(): void
    {
        $this->loginGestor();
        $ida = Viagem::factory()->create([
            'veiculo_id' => Veiculo::factory()->create(['km_atual' => 4000])->id,
            'km_saida' => 5000, 'motivo_viagem' => 'transferencia_paciente',
        ]);

        $res = $this->patchJson("/api/viagens/{$ida->id}/chegada", ['km_chegada' => 5120, 'retornar_origem' => true])
            ->assertJsonPath('viagem_retorno.eh_retorno', true)
            ->assertJsonPath('data.eh_retorno', false);
        $retornoId = $res->json('viagem_retorno.id');
        $this->assertDatabaseHas('viagens', ['id' => $retornoId, 'viagem_ida_id' => $ida->id]);

        $this->patchJson("/api/viagens/{$retornoId}/chegada", ['km_chegada' => 5240, 'retornar_origem' => true])
            ->assertJsonValidationErrors(['retornar_origem']);
        $this->assertSame(2, Viagem::count());

        $this->patchJson("/api/viagens/{$retornoId}/chegada", ['km_chegada' => 5240])
            ->assertOk()->assertJsonPath('data.status', 'concluida');
    }

    public function test_motivo_retorno_nao_pode_ser_escolhido_manualmente(): void
    {
        $this->loginAdmin();
        $viagem = Viagem::factory()->create(['motivo_viagem' => 'tfd']);

        $this->putJson("/api/viagens/{$viagem->id}", ['motivo_viagem' => 'retorno'])
            ->assertJsonValidationErrors(['motivo_viagem']);
    }

    public function test_viagem_de_retorno_pode_ser_editada_mantendo_o_motivo(): void
    {
        $this->loginAdmin();
        $viagem = Viagem::factory()->create(['motivo_viagem' => 'retorno']);

        $this->putJson("/api/viagens/{$viagem->id}", ['motivo_viagem' => 'retorno', 'origem' => 'X'])->assertOk();
    }
}
