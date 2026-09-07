<?php

namespace Tests\Feature\Auth;

use App\Models\Unidade;
use App\Models\UnidadeLdapConfiguracao;
use App\Models\Usuario;
use Illuminate\Support\Str;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use LdapRecord\Testing\ConnectionFake;
use Tests\TestCase;

class LoginAdTest extends TestCase
{
    /**
     * Cria uma Unidade + UnidadeLdapConfiguracao ativa (ou não, via
     * overrides) e prepara um fake do DirectoryEmulator para a conexão
     * nomeada 'unidade-ldap-{id}' que AuthController::resolverLoginLdap
     * registraria em produção.
     *
     * DirectoryEmulator::setup() exige que a conexão já esteja registrada
     * no Container (ele lê a config dela para clonar antes de substituir
     * pela fake) — por isso registramos aqui uma Connection real (nunca
     * chega a conectar de fato) antes de chamar setup(). O controller,
     * ao rodar, encontra a conexão já registrada com esse nome e não a
     * sobrescreve, preservando a fake.
     */
    private function criarUnidadeComLdap(array $overrides = []): array
    {
        $unidade = Unidade::factory()->create();
        $config = UnidadeLdapConfiguracao::factory()->create(array_merge([
            'unidade_id' => $unidade->id,
        ], $overrides));

        $nomeConexao = 'unidade-ldap-'.$config->id;

        Container::getInstance()->getConnectionManager()->addConnection(
            new Connection($config->paraConexaoLdap()),
            $nomeConexao
        );

        $fake = DirectoryEmulator::setup($nomeConexao);

        return [$unidade, $config, $fake];
    }

    protected function tearDown(): void
    {
        DirectoryEmulator::tearDown();
        parent::tearDown();
    }

    private function criarUsuarioLdap(string $nomeConexao, ConnectionFake $fake, array $attrs = [], bool $autorizarBind = true): LdapUser
    {
        $user = new LdapUser(array_merge([
            'cn' => 'João Silva',
            'displayname' => 'João Silva',
            'samaccountname' => 'jsilva',
            'mail' => 'jsilva@empresa.com.br',
            'objectguid' => Str::uuid()->toString(),
        ], $attrs));

        $user->setConnection($nomeConexao);
        $user->save();

        if ($autorizarBind) {
            $fake->actingAs($user);
        }

        return $user;
    }

    public function test_login_ad_com_credenciais_validas_na_unica_unidade_cria_usuario_solicitante(): void
    {
        [$unidade, $config, $fake] = $this->criarUnidadeComLdap();
        $this->criarUsuarioLdap('unidade-ldap-'.$config->id, $fake);

        $response = $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-correta',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'nome', 'email', 'perfil']])
            ->assertJsonPath('user.perfil', 'solicitante')
            ->assertJsonPath('user.unidade_id', $unidade->id);

        $this->assertDatabaseHas('usuarios', [
            'email' => 'jsilva@empresa.com.br',
            'perfil' => 'solicitante',
        ]);
    }

    public function test_login_ad_tenta_proxima_unidade_quando_primeira_nao_tem_o_usuario(): void
    {
        [, $configA, $fakeA] = $this->criarUnidadeComLdap();
        [$unidadeB, $configB, $fakeB] = $this->criarUnidadeComLdap();

        // Unidade A não tem esse usuário no seu diretório (fake vazia,
        // nenhum LdapUser criado nela); unidade B tem.
        $this->criarUsuarioLdap('unidade-ldap-'.$configB->id, $fakeB);

        $response = $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-correta',
        ]);

        $response->assertOk()->assertJsonPath('user.unidade_id', $unidadeB->id);
    }

    public function test_login_ad_ignora_unidade_com_configuracao_inativa(): void
    {
        [$unidadeInativa, $configInativa, $fakeInativa] = $this->criarUnidadeComLdap(['ativo' => false]);
        $this->criarUsuarioLdap('unidade-ldap-'.$configInativa->id, $fakeInativa);

        $response = $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-correta',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_ad_com_senha_incorreta_em_todas_unidades_retorna_401(): void
    {
        [, $config, $fake] = $this->criarUnidadeComLdap();
        $this->criarUsuarioLdap('unidade-ldap-'.$config->id, $fake, [], autorizarBind: false);

        $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-errada',
        ])->assertUnauthorized()
            ->assertJson(['error' => 'Usuário ou senha inválidos']);
    }

    public function test_login_ad_sem_nenhuma_unidade_configurada_retorna_401(): void
    {
        $this->postJson('/api/auth/login-ad', [
            'usuario' => 'naoexiste',
            'senha' => 'qualquer',
        ])->assertUnauthorized()
            ->assertJson(['error' => 'Usuário ou senha inválidos']);
    }

    public function test_login_ad_com_usuario_existente_atualiza_dados(): void
    {
        [, $config, $fake] = $this->criarUnidadeComLdap();
        $ldapUser = $this->criarUsuarioLdap('unidade-ldap-'.$config->id, $fake);
        $guid = $ldapUser->getConvertedGuid();

        $usuario = Usuario::factory()->create([
            'ldap_guid' => $guid,
            'nome' => 'Nome Antigo',
            'perfil' => 'solicitante',
        ]);

        $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-correta',
        ])->assertOk();

        $this->assertDatabaseHas('usuarios', [
            'id' => $usuario->id,
            'nome' => 'João Silva',
        ]);
    }

    public function test_login_ad_com_guid_ausente_nao_cria_nem_altera_usuario(): void
    {
        [, $config, $fake] = $this->criarUnidadeComLdap();
        $countAntes = Usuario::count();

        $nomeConexao = 'unidade-ldap-'.$config->id;
        $this->criarUsuarioLdap($nomeConexao, $fake);

        // Remove o guid persistido na fake (a EmulatedBuilder monta o
        // atributo objectguid a partir das colunas guid/guid_key do
        // registro, não da tabela de atributos), simulando um retorno do
        // AD sem esse atributo (guid inutilizável).
        $ldapObject = \LdapRecord\Laravel\Testing\LdapObject::on($nomeConexao)->latest('id')->firstOrFail();
        $ldapObject->attributes()->where('name', 'objectguid')->delete();
        $ldapObject->forceFill(['guid_key' => null])->save();

        $response = $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-correta',
        ]);

        $response->assertStatus(503);
        $this->assertDatabaseCount('usuarios', $countAntes);
    }

    public function test_login_ad_com_email_de_conta_existente_vincula_sem_alterar_perfil(): void
    {
        $unidadeAntiga = Unidade::factory()->create();
        $admin = Usuario::factory()->create([
            'nome' => 'Fernando Admin',
            'email' => 'jsilva@empresa.com.br',
            'perfil' => 'admin',
            'unidade_id' => $unidadeAntiga->id,
            'ldap_guid' => null,
        ]);

        [, $config, $fake] = $this->criarUnidadeComLdap();
        $ldapUser = $this->criarUsuarioLdap('unidade-ldap-'.$config->id, $fake);

        $response = $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-correta',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $admin->id)
            ->assertJsonPath('user.perfil', 'admin')
            ->assertJsonPath('user.unidade_id', $unidadeAntiga->id);

        $this->assertDatabaseHas('usuarios', [
            'id' => $admin->id,
            'nome' => 'Fernando Admin',
            'perfil' => 'admin',
            'unidade_id' => $unidadeAntiga->id,
            'ldap_guid' => $ldapUser->getConvertedGuid(),
        ]);

        $this->assertDatabaseCount('usuarios', 1);
    }

    public function test_login_ad_com_usuario_inativo_retorna_401_e_nao_reativa(): void
    {
        [, $config, $fake] = $this->criarUnidadeComLdap();
        $ldapUser = $this->criarUsuarioLdap('unidade-ldap-'.$config->id, $fake);
        $guid = $ldapUser->getConvertedGuid();

        $usuario = Usuario::factory()->create([
            'ldap_guid' => $guid,
            'nome' => 'Nome Antigo',
            'perfil' => 'solicitante',
            'ativo' => false,
        ]);

        $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-correta',
        ])->assertStatus(401)
            ->assertJson(['error' => 'Usuário ou senha inválidos']);

        $this->assertDatabaseHas('usuarios', [
            'id' => $usuario->id,
            'ativo' => false,
        ]);
    }

    public function test_login_ad_erro_de_conexao_em_uma_unidade_nao_impede_tentar_a_proxima(): void
    {
        [, $configA, $fakeA] = $this->criarUnidadeComLdap();
        [$unidadeB, $configB, $fakeB] = $this->criarUnidadeComLdap();

        // Unidade A: simula uma falha de conectividade/consulta ao AD
        // fazendo a operação de busca da fake LDAP lançar a exceção que
        // o LdapRecord lançaria em uma falha real (timeout, host fora do
        // ar, etc.), sem depender de rede real no teste.
        $fakeA->getLdapConnection()->expect(
            \LdapRecord\Testing\LdapFake::operation('search')->andThrow(
                new \LdapRecord\LdapRecordException('Falha simulada de conexão com o AD')
            )
        );

        $this->criarUsuarioLdap('unidade-ldap-'.$configB->id, $fakeB);

        $response = $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-correta',
        ]);

        $response->assertOk()->assertJsonPath('user.unidade_id', $unidadeB->id);
    }
}
