<?php

namespace Tests\Feature\Colaborador;

use App\Models\Colaborador;
use App\Models\Unidade;
use App\Models\UnidadeLdapConfiguracao;
use App\Services\ColaboradorSyncService;
use Illuminate\Support\Str;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use Tests\TestCase;

class ColaboradorTest extends TestCase
{
    protected function tearDown(): void
    {
        DirectoryEmulator::tearDown();
        parent::tearDown();
    }

    /** Ver LoginAdTest::criarUnidadeComLdap para o porquê da conexão real antes do setup(). */
    private function criarUnidadeComLdap(array $overrides = []): array
    {
        $unidade = Unidade::factory()->create();
        $config = UnidadeLdapConfiguracao::factory()->create(array_merge([
            'unidade_id' => $unidade->id,
            'base_dn' => 'dc=empresa,dc=local',
        ], $overrides));

        $nomeConexao = 'unidade-ldap-'.$config->id;
        Container::getInstance()->getConnectionManager()->addConnection(
            new Connection($config->paraConexaoLdap()),
            $nomeConexao
        );
        DirectoryEmulator::setup($nomeConexao);

        return [$unidade, $config, $nomeConexao];
    }

    private function criarUsuarioAd(string $conexao, string $dn, array $attrs = []): LdapUser
    {
        $user = new LdapUser(array_merge([
            'cn' => 'x',
            'displayname' => 'Maria Souza',
            'samaccountname' => 'msouza',
            'objectguid' => Str::uuid()->toString(),
            'useraccountcontrol' => 512,
        ], $attrs));
        $user->setConnection($conexao);
        $user->setDn($dn);
        $user->save();

        return $user;
    }

    public function test_sincroniza_somente_usuarios_da_ou_e_marca_desabilitados_como_inativos(): void
    {
        [$unidade, $config, $conexao] = $this->criarUnidadeComLdap(['ou_colaboradores' => 'ou=Colaboradores,dc=empresa,dc=local']);

        $this->criarUsuarioAd($conexao, 'cn=Maria Souza,ou=Colaboradores,dc=empresa,dc=local');
        $this->criarUsuarioAd($conexao, 'cn=Joao Desligado,ou=Colaboradores,dc=empresa,dc=local', [
            'displayname' => 'João Desligado', 'samaccountname' => 'jdesl', 'useraccountcontrol' => 514,
        ]);
        $this->criarUsuarioAd($conexao, 'cn=Fora Da OU,ou=Servicos,dc=empresa,dc=local', [
            'displayname' => 'Conta de Serviço', 'samaccountname' => 'svc',
        ]);

        $r = app(ColaboradorSyncService::class)->sincronizar($config);

        $this->assertTrue($r['ok']);
        $this->assertDatabaseHas('colaboradores', ['nome' => 'MARIA SOUZA', 'unidade_id' => $unidade->id, 'ativo' => true]);
        $this->assertDatabaseHas('colaboradores', ['nome' => 'JOÃO DESLIGADO', 'ativo' => false]);
        $this->assertDatabaseMissing('colaboradores', ['samaccountname' => 'svc']);
    }

    public function test_colaborador_que_saiu_do_ad_fica_inativo(): void
    {
        [$unidade, $config] = $this->criarUnidadeComLdap();
        $antigo = Colaborador::factory()->create(['unidade_id' => $unidade->id]);

        app(ColaboradorSyncService::class)->sincronizar($config);

        $this->assertFalse($antigo->fresh()->ativo);
    }

    public function test_busca_retorna_apenas_ativos_filtrando_por_nome_e_unidade(): void
    {
        $this->loginOperador();
        $u1 = Unidade::factory()->create();
        $u2 = Unidade::factory()->create();
        Colaborador::factory()->create(['nome' => 'ANA PAULA', 'unidade_id' => $u1->id]);
        Colaborador::factory()->create(['nome' => 'ANA LÚCIA', 'unidade_id' => $u2->id]);
        Colaborador::factory()->create(['nome' => 'ANA INATIVA', 'unidade_id' => $u1->id, 'ativo' => false]);
        Colaborador::factory()->create(['nome' => 'BRUNO', 'unidade_id' => $u1->id]);

        $this->getJson('/api/colaboradores?busca=ana')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/colaboradores?busca=ana&unidade_id='.$u1->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nome', 'ANA PAULA');
    }

    public function test_sincronizacao_manual_exige_admin(): void
    {
        $this->loginOperador();
        $this->postJson('/api/colaboradores/sincronizar')->assertForbidden();
    }
}
