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
        $veiculo = Veiculo::factory()->create(['km_atual' => 4900, 'modelo' => 'STRADA']);
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
        $veiculo = Veiculo::factory()->create(['km_atual' => 4900, 'modelo' => 'STRADA']);
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
        $veiculo = Veiculo::factory()->create(['km_atual' => 4900, 'modelo' => 'AMBULANCIA']);
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
        $veiculo = Veiculo::factory()->create(['km_atual' => 4900, 'modelo' => 'STRADA']);
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

    public function test_operador_ve_apenas_viagens_do_mes_corrente_e_as_em_andamento(): void
    {
        $usuario = $this->loginOperador();
        $motorista = Motorista::factory()->create();
        $usuario->update(['motorista_id' => $motorista->id]);

        $doMes = Viagem::factory()->create(['motorista_id' => $motorista->id, 'status' => 'concluida', 'saida_at' => now()->startOfMonth()->addHour()]);
        $mesPassado = Viagem::factory()->create(['motorista_id' => $motorista->id, 'status' => 'concluida', 'saida_at' => now()->startOfMonth()->subDay()]);
        $abertaMesPassado = Viagem::factory()->create(['motorista_id' => $motorista->id, 'status' => 'em_andamento', 'saida_at' => now()->startOfMonth()->subDay()]);

        $ids = collect($this->getJson('/api/viagens')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($doMes->id));
        $this->assertTrue($ids->contains($abertaMesPassado->id));
        $this->assertFalse($ids->contains($mesPassado->id));
    }

    public function test_admin_recebe_viagens_paginadas_de_20_em_20(): void
    {
        $this->loginAdmin();
        Viagem::factory()->count(25)->create();

        $this->getJson('/api/viagens?page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.last_page', 2);
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
        $ambulancia = Veiculo::factory()->create(['km_atual' => 4900, 'modelo' => 'AMBULÂNCIA SPRINTER']);
        $strada = Veiculo::factory()->create(['km_atual' => 4900, 'modelo' => 'STRADA']);
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

    public function test_tfd_exige_destino_no_formato_cidade_uf(): void
    {
        $this->loginGestor();
        $ambulancia = Veiculo::factory()->create(['km_atual' => 4900, 'modelo' => 'AMBULÂNCIA SPRINTER']);
        $motorista = Motorista::factory()->create();
        $this->liberarChecklist($ambulancia, $motorista);

        $payload = fn ($destino) => [
            'veiculo_id' => $ambulancia->id, 'motorista_id' => $motorista->id,
            'origem' => 'UPA', 'destino' => $destino, 'km_saida' => 5000, 'motivo_viagem' => 'tfd',
        ];

        $this->postJson('/api/viagens', $payload('RECIFE'))->assertJsonValidationErrors(['destino']);
        $this->postJson('/api/viagens', $payload('RECIFE/PE'))->assertCreated();
    }

    public function test_motivo_inativo_e_recusado_e_ambos_aceito_nos_dois_tipos(): void
    {
        $this->loginGestor();
        $ambulancia = Veiculo::factory()->create(['km_atual' => 4900, 'modelo' => 'AMBULÂNCIA SPRINTER']);
        $strada = Veiculo::factory()->create(['km_atual' => 4900, 'modelo' => 'STRADA']);
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
            'veiculo_id' => Veiculo::factory()->create(['km_atual' => 4900])->id,
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

    public function test_chegada_de_tfd_com_retorno_abre_viagem_de_retorno(): void
    {
        $this->loginGestor();
        $viagem = Viagem::factory()->create([
            'veiculo_id' => Veiculo::factory()->create(['km_atual' => 4900, 'modelo' => 'AMBULÂNCIA SPRINTER'])->id,
            'km_saida' => 5000, 'origem' => 'UPA', 'destino' => 'RECIFE/PE', 'motivo_viagem' => 'tfd', 'saida_at' => now()->subHours(5),
        ]);

        $res = $this->patchJson("/api/viagens/{$viagem->id}/chegada", ['km_chegada' => 5300, 'retornar_origem' => true])
            ->assertOk()
            ->assertJsonPath('viagem_retorno.origem', 'RECIFE/PE')
            ->assertJsonPath('viagem_retorno.destino', 'UPA');

        $this->assertDatabaseHas('viagens', [
            'id' => $res->json('viagem_retorno.id'), 'status' => 'em_andamento',
            'km_saida' => 5300, 'motivo_viagem' => 'retorno', 'viagem_ida_id' => $viagem->id,
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

    public function test_retorno_so_vale_para_transferencia_de_paciente_e_tfd(): void
    {
        $this->loginGestor();
        $viagem = Viagem::factory()->create(['km_saida' => 5000, 'motivo_viagem' => 'buscar_medico']);

        $this->patchJson("/api/viagens/{$viagem->id}/chegada", ['km_chegada' => 5120, 'retornar_origem' => true])
            ->assertJsonValidationErrors(['retornar_origem']);

        $this->assertDatabaseHas('viagens', ['id' => $viagem->id, 'status' => 'em_andamento']);
    }

    public function test_viagem_de_retorno_nao_permite_novo_retorno(): void
    {
        $this->loginGestor();
        $ida = Viagem::factory()->create([
            'veiculo_id' => Veiculo::factory()->create(['km_atual' => 4900])->id,
            'km_saida' => 5000, 'motivo_viagem' => 'transferencia_paciente',
        ]);

        $res = $this->patchJson("/api/viagens/{$ida->id}/chegada", ['km_chegada' => 5120, 'retornar_origem' => true])
            ->assertJsonPath('viagem_retorno.eh_retorno', true)
            ->assertJsonPath('data.eh_retorno', false);
        $retornoId = $res->json('viagem_retorno.id');
        $this->assertDatabaseHas('viagens', ['id' => $retornoId, 'viagem_ida_id' => $ida->id]);
        $this->travel(2)->hours();

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

    private function viagemAberta(int $kmAtual = 50000, int $kmSaida = 50010, int $horasAtras = 1): Viagem
    {
        return Viagem::factory()->create([
            'veiculo_id' => Veiculo::factory()->create(['km_atual' => $kmAtual])->id,
            'km_saida' => $kmSaida,
            'saida_at' => now()->subHours($horasAtras),
            'motivo_viagem' => 'buscar_medico',
        ]);
    }

    public function test_saida_com_digito_a_mais_e_bloqueada(): void
    {
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create(['km_atual' => 65432, 'modelo' => 'STRADA']);
        $motorista = Motorista::factory()->create();
        $this->liberarChecklist($veiculo, $motorista);
        $payload = ['veiculo_id' => $veiculo->id, 'motorista_id' => $motorista->id, 'origem' => 'a', 'destino' => 'b', 'motivo_viagem' => 'buscar_medico'];

        // Dígito a mais e dígito inicial trocado (6 → 9): bloqueio, mesmo confirmando.
        foreach ([654320, 95432] as $km) {
            $this->postJson('/api/viagens', $payload + ['km_saida' => $km, 'confirmar_km' => true, 'justificativa_km' => 'odômetro ok'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['km_saida'])
                ->assertJsonMissingPath('km_confirmacao_necessaria');
        }
        $this->assertSame(0, Viagem::count());
    }

    public function test_salto_moderado_na_saida_exige_confirmacao_e_marca_suspeito(): void
    {
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create(['km_atual' => 65432, 'modelo' => 'STRADA']);
        $motorista = Motorista::factory()->create();
        $this->liberarChecklist($veiculo, $motorista);
        $payload = ['veiculo_id' => $veiculo->id, 'motorista_id' => $motorista->id, 'origem' => 'a', 'destino' => 'b', 'motivo_viagem' => 'buscar_medico', 'km_saida' => 66000];

        $this->postJson('/api/viagens', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('km_confirmacao_necessaria', true)
            ->assertJsonPath('km_referencia', 65432);

        $this->postJson('/api/viagens', $payload + ['confirmar_km' => true, 'justificativa_km' => 'Veículo voltou de oficina externa'])
            ->assertCreated()
            ->assertJsonPath('data.km_suspeito', true);
        $this->assertDatabaseHas('viagens', ['km_saida' => 66000, 'km_suspeito' => true]);
    }

    public function test_ultimo_km_considera_viagens_e_nao_so_km_atual(): void
    {
        $this->loginGestor();
        $veiculo = Veiculo::factory()->create(['km_atual' => 10000, 'modelo' => 'STRADA']);
        Viagem::factory()->create(['veiculo_id' => $veiculo->id, 'km_saida' => 12000, 'km_chegada' => 12100, 'status' => 'concluida']);
        $motorista = Motorista::factory()->create();
        $this->liberarChecklist($veiculo, $motorista);

        $this->postJson('/api/viagens', ['veiculo_id' => $veiculo->id, 'motorista_id' => $motorista->id, 'origem' => 'a', 'destino' => 'b', 'motivo_viagem' => 'buscar_medico', 'km_saida' => 12120])
            ->assertCreated()
            ->assertJsonPath('data.km_suspeito', false);
    }

    public function test_chegada_com_digito_a_mais_e_bloqueada(): void
    {
        $this->loginGestor();
        $viagem = $this->viagemAberta();

        $this->patchJson("/api/viagens/{$viagem->id}/chegada", ['km_chegada' => 500400, 'confirmar_km' => true, 'justificativa_km' => 'ok ok ok'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['km_chegada']);
        $this->assertSame('em_andamento', $viagem->fresh()->status);
    }

    public function test_chegada_incompativel_com_tempo_exige_justificativa(): void
    {
        $this->loginGestor();
        $viagem = $this->viagemAberta(horasAtras: 1); // limite: 110 km/h × 1h + 20

        $this->patchJson("/api/viagens/{$viagem->id}/chegada", ['km_chegada' => 50110])->assertOk();

        $outra = $this->viagemAberta(horasAtras: 1);
        $this->patchJson("/api/viagens/{$outra->id}/chegada", ['km_chegada' => 50400])
            ->assertUnprocessable()
            ->assertJsonPath('km_confirmacao_necessaria', true);

        $this->patchJson("/api/viagens/{$outra->id}/chegada", ['km_chegada' => 50400, 'confirmar_km' => true, 'justificativa_km' => 'Esqueci de registrar a chegada'])
            ->assertOk()
            ->assertJsonPath('data.km_suspeito', true);
        $this->assertStringContainsString('Chegada: Esqueci', $outra->fresh()->justificativa_km);
    }

    public function test_tfd_longo_com_tempo_compativel_passa(): void
    {
        $this->loginGestor();
        $viagem = $this->viagemAberta(horasAtras: 8);

        $this->patchJson("/api/viagens/{$viagem->id}/chegada", ['km_chegada' => 50010 + 700])
            ->assertOk()
            ->assertJsonPath('data.km_suspeito', false);
    }
}
