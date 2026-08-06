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
}
