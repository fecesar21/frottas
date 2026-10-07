<?php

namespace Tests\Feature\Frota;

use App\Models\Checkin;
use App\Models\Motorista;
use App\Models\Unidade;
use App\Models\Usuario;
use App\Models\Veiculo;
use App\Models\Viagem;
use Tests\TestCase;

class FrotaStatusApiTest extends TestCase
{
    private Unidade $unidade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unidade = Unidade::factory()->create();
    }

    private function emPlantao(string $placa, ?Unidade $unidade = null): array
    {
        $unidade ??= $this->unidade;
        $motorista = Motorista::factory()->create();
        Usuario::factory()->create(['perfil' => 'operador', 'motorista_id' => $motorista->id]);
        $veiculo = Veiculo::factory()->create(['placa' => $placa, 'status' => 'em_uso']);
        $motorista->unidades()->attach($unidade);
        $veiculo->unidades()->attach($unidade);
        Checkin::factory()->create(['motorista_id' => $motorista->id, 'veiculo_id' => $veiculo->id]);

        return [$motorista, $veiculo];
    }

    private function comoSolicitante(): void
    {
        $u = Usuario::factory()->create(['perfil' => 'solicitante', 'unidade_id' => $this->unidade->id]);
        $this->withToken($u->createToken('t')->plainTextToken);
    }

    public function test_solicitante_ve_status_da_frota_da_sua_unidade(): void
    {
        [, $livre] = $this->emPlantao('AAA1111');
        [$motViagem, $emViagem] = $this->emPlantao('BBB2222');
        Viagem::factory()->create(['motorista_id' => $motViagem->id, 'veiculo_id' => $emViagem->id, 'status' => 'em_andamento']);
        $oficina = Veiculo::factory()->create(['placa' => 'CCC3333', 'status' => 'manutencao']);
        $oficina->unidades()->attach($this->unidade);
        Veiculo::factory()->create(['placa' => 'DDD4444', 'status' => 'disponivel'])->unidades()->attach($this->unidade);
        $this->emPlantao('EEE5555', Unidade::factory()->create());

        $this->comoSolicitante();
        $res = $this->getJson('/api/frota/status')->assertOk();

        $status = collect($res->json('veiculos'))->pluck('status', 'placa')->all();
        $this->assertSame(['AAA1111' => 'disponivel', 'BBB2222' => 'em_viagem', 'CCC3333' => 'manutencao'], $status);
        $this->assertEqualsCanonicalizing(['disponivel', 'em_viagem'], collect($res->json('motoristas'))->pluck('status')->all());
        $this->assertArrayNotHasKey('cpf', $res->json('motoristas.0'));
    }

    public function test_exige_autenticacao(): void
    {
        $this->getJson('/api/frota/status')->assertUnauthorized();
    }
}
