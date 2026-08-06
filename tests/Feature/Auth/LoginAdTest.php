<?php

namespace Tests\Feature\Auth;

use App\Models\Unidade;
use App\Models\UnidadeAdMapeamento;
use App\Models\Usuario;
use Illuminate\Support\Str;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use Tests\TestCase;

class LoginAdTest extends TestCase
{
    protected $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = DirectoryEmulator::setup('default');
    }

    protected function tearDown(): void
    {
        DirectoryEmulator::tearDown();
        parent::tearDown();
    }

    /**
     * Cria um usuário no diretório LDAP fake. Por padrão também autoriza o
     * bind (qualquer senha) para esse DN via actingAs(), simulando login
     * com credenciais válidas.
     */
    private function criarUsuarioLdap(array $attrs = [], bool $autorizarBind = true): LdapUser
    {
        $user = LdapUser::create(array_merge([
            'cn' => 'João Silva',
            'displayname' => 'João Silva',
            'samaccountname' => 'jsilva',
            'mail' => 'jsilva@empresa.com.br',
            'department' => 'HOSP-CENTRO',
            'objectguid' => Str::uuid()->toString(),
        ], $attrs));

        if ($autorizarBind) {
            $this->fake->actingAs($user);
        }

        return $user;
    }

    public function test_login_ad_com_credenciais_validas_cria_usuario_solicitante(): void
    {
        $unidade = Unidade::factory()->create();
        UnidadeAdMapeamento::create(['valor_ad' => 'HOSP-CENTRO', 'unidade_id' => $unidade->id]);
        $this->criarUsuarioLdap();

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

    public function test_login_ad_com_usuario_existente_atualiza_dados(): void
    {
        $ldapUser = $this->criarUsuarioLdap();
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

    public function test_login_ad_com_senha_incorreta_retorna_401(): void
    {
        // Não autorizamos o bind (não chamamos actingAs), então qualquer
        // tentativa de bind para este usuário falha no diretório fake,
        // simulando uma senha incorreta.
        $this->criarUsuarioLdap([], autorizarBind: false);

        $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-errada',
        ])->assertUnauthorized()
            ->assertJson(['error' => 'Usuário ou senha inválidos']);
    }

    public function test_login_ad_com_usuario_inexistente_retorna_401(): void
    {
        $this->postJson('/api/auth/login-ad', [
            'usuario' => 'naoexiste',
            'senha' => 'qualquer',
        ])->assertUnauthorized()
            ->assertJson(['error' => 'Usuário ou senha inválidos']);
    }

    public function test_login_ad_sem_mapeamento_de_unidade_cria_usuario_com_unidade_nula(): void
    {
        $this->criarUsuarioLdap(['department' => 'SETOR-SEM-MAPEAMENTO']);

        $response = $this->postJson('/api/auth/login-ad', [
            'usuario' => 'jsilva',
            'senha' => 'senha-correta',
        ]);

        $response->assertOk()->assertJsonPath('user.unidade_id', null);
    }

    public function test_login_ad_com_guid_ausente_nao_cria_nem_altera_usuario(): void
    {
        $countAntes = Usuario::count();

        $this->criarUsuarioLdap();

        // O DirectoryEmulator sempre popula um GUID na criação (a partir do
        // atributo 'objectguid' informado, ou gerando um automaticamente).
        // Para simular um GUID ausente/vazio vindo do AD, esvaziamos o
        // valor diretamente no registro subjacente do diretório fake após
        // a criação — tanto na coluna dedicada `guid` quanto no atributo
        // LDAP `objectguid` armazenado, já que ambos alimentam o resultado
        // retornado pela consulta emulada.
        $ldapObject = \LdapRecord\Laravel\Testing\LdapObject::query()->firstOrFail();
        $ldapObject->update(['guid' => '']);
        $ldapObject->attributes()->where('name', 'objectguid')->delete();

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

        $unidadeNovaDoAd = Unidade::factory()->create();
        UnidadeAdMapeamento::create(['valor_ad' => 'HOSP-CENTRO', 'unidade_id' => $unidadeNovaDoAd->id]);
        $ldapUser = $this->criarUsuarioLdap();

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
        $ldapUser = $this->criarUsuarioLdap();
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
}
