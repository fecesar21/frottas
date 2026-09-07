<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnidadeLdapConfiguracao extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'unidade_ldap_configuracoes';

    protected $fillable = [
        'unidade_id', 'host', 'port', 'base_dn', 'username', 'password',
        'use_ssl', 'use_starttls', 'unidade_attribute', 'valores_ad', 'ativo',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'encrypted',
        'valores_ad' => 'array',
        'use_ssl' => 'boolean',
        'use_starttls' => 'boolean',
        'ativo' => 'boolean',
        'port' => 'integer',
    ];

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function paraConexaoLdap(): array
    {
        return [
            'hosts' => [$this->host],
            'port' => $this->port,
            'base_dn' => $this->base_dn,
            'username' => $this->username,
            'password' => $this->password,
            'use_ssl' => $this->use_ssl,
            'use_tls' => $this->use_starttls,
            'timeout' => 5,
            'options' => [
                LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_NEVER,
            ],
        ];
    }
}
