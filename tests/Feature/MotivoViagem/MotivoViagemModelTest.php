<?php

namespace Tests\Feature\MotivoViagem;

use App\Models\MotivoViagem;
use App\Models\Veiculo;
use App\Models\Viagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotivoViagemModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_semeia_os_8_motivos_de_sistema(): void
    {
        $this->assertSame(9, MotivoViagem::where('sistema', true)->count()); // 8 + retorno automático
        $this->assertSame('ambulancia', MotivoViagem::porCodigo('tfd')->tipo_veiculo);
        $this->assertFalse(MotivoViagem::porCodigo('alimentacao')->disponivel_solicitacao);
        $this->assertTrue(MotivoViagem::porCodigo('buscar_medico')->disponivel_solicitacao);
    }

    public function test_rotulo_usa_nome_cadastrado_e_fallbacks(): void
    {
        $this->assertSame('Serviços Administrativos Diversos', MotivoViagem::rotulo('servicos_administrativos'));
        $this->assertSame('Não informado', MotivoViagem::rotulo(null));
        $this->assertSame('Motivo Legado', MotivoViagem::rotulo('motivo_legado'));
        $this->assertSame('TFD', Viagem::rotuloMotivo('tfd'));
    }

    public function test_permite_veiculo_por_tipo(): void
    {
        $amb = Veiculo::factory()->make(['modelo' => 'AMBULÂNCIA X']);
        $adm = Veiculo::factory()->make(['modelo' => 'STRADA']);
        $ambos = MotivoViagem::factory()->create(['tipo_veiculo' => 'ambos']);

        $this->assertTrue(MotivoViagem::porCodigo('tfd')->permiteVeiculo($amb));
        $this->assertFalse(MotivoViagem::porCodigo('tfd')->permiteVeiculo($adm));
        $this->assertTrue($ambos->permiteVeiculo($amb));
        $this->assertTrue($ambos->permiteVeiculo($adm));
    }

    public function test_gerar_codigo_evita_colisao(): void
    {
        $this->assertSame('alimentacao_2', MotivoViagem::gerarCodigo('Alimentação'));
        $this->assertSame('lavar_veiculo', MotivoViagem::gerarCodigo('Lavar Veículo'));
    }
}
