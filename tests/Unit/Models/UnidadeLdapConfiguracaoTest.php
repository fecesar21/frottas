<?php

namespace Tests\Unit\Models;

use App\Models\UnidadeLdapConfiguracao;
use Tests\TestCase;

class UnidadeLdapConfiguracaoTest extends TestCase
{
    public function test_password_e_criptografada_e_oculta_na_serializacao(): void
    {
        $config = UnidadeLdapConfiguracao::factory()->create(['password' => 'senha-em-claro']);

        $bruto = \DB::table('unidade_ldap_configuracoes')->find($config->id);
        $this->assertNotEquals('senha-em-claro', $bruto->password);

        $config->refresh();
        $this->assertEquals('senha-em-claro', $config->password);
        $this->assertArrayNotHasKey('password', $config->toArray());
    }

    public function test_para_conexao_ldap_monta_array_de_conexao_ldaprecord(): void
    {
        $config = UnidadeLdapConfiguracao::factory()->make([
            'host' => '10.0.0.5',
            'port' => 636,
            'base_dn' => 'DC=empresa,DC=local',
            'username' => 'svc@empresa.local',
            'password' => 'segredo',
            'use_ssl' => true,
            'use_starttls' => false,
        ]);

        $conexao = $config->paraConexaoLdap();

        $this->assertSame(['10.0.0.5'], $conexao['hosts']);
        $this->assertSame(636, $conexao['port']);
        $this->assertSame('DC=empresa,DC=local', $conexao['base_dn']);
        $this->assertSame('svc@empresa.local', $conexao['username']);
        $this->assertSame('segredo', $conexao['password']);
        $this->assertArrayNotHasKey('use_ssl', $conexao);
        $this->assertTrue($conexao['use_tls']);
        $this->assertFalse($conexao['use_starttls']);
    }

    public function test_para_conexao_ldap_e_aceita_pelo_ldaprecord_sem_erro_de_configuracao(): void
    {
        $config = UnidadeLdapConfiguracao::factory()->make();

        // LdapRecord\Configuration\DomainConfiguration lança ConfigurationException
        // para qualquer chave que não reconheça (ex.: 'use_ssl' não existe — só
        // 'use_tls'/'use_starttls'). Construir a Connection real aqui garante que
        // paraConexaoLdap() nunca regride para uma chave inválida.
        $conexaoLdapRecord = new \LdapRecord\Connection($config->paraConexaoLdap());

        $this->assertTrue($conexaoLdapRecord->getConfiguration()->get('use_tls'));
    }
}
