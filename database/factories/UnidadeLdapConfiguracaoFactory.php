<?php

namespace Database\Factories;

use App\Models\Unidade;
use App\Models\UnidadeLdapConfiguracao;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnidadeLdapConfiguracaoFactory extends Factory
{
    protected $model = UnidadeLdapConfiguracao::class;

    public function definition(): array
    {
        return [
            'unidade_id' => Unidade::factory(),
            'host' => fake()->domainName(),
            'port' => 636,
            'base_dn' => 'DC=' . fake()->domainWord() . ',DC=local',
            'username' => 'svc-ldap@' . fake()->domainName(),
            'password' => 'senha-service-account',
            'use_ssl' => true,
            'use_starttls' => false,
            'unidade_attribute' => 'department',
            'valores_ad' => [fake()->word()],
            'ativo' => true,
        ];
    }
}
