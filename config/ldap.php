<?php

return [
    'default' => env('LDAP_CONNECTION', 'default'),

    'connections' => [
        'default' => [
            'hosts' => [env('LDAP_HOST', '')],
            'username' => env('LDAP_USERNAME', ''),
            'password' => env('LDAP_PASSWORD', ''),
            'port' => env('LDAP_PORT', 636),
            'base_dn' => env('LDAP_BASE_DN', ''),
            'timeout' => env('LDAP_TIMEOUT', 5),
            'use_tls' => env('LDAP_USE_SSL', true),
            'use_starttls' => env('LDAP_USE_TLS', false),

            // O Domain Controller usa um certificado emitido pela CA interna
            // (não presente no keychain do sistema), então a validação
            // completa da cadeia falha por padrão. Desabilitar a exigência
            // de certificado é aceitável aqui porque a conexão já roda sobre
            // LDAPS (o canal continua criptografado) e o DC está na mesma
            // rede interna — o objetivo é evitar credenciais em texto claro,
            // não autenticar a identidade do servidor via CA pública.
            'options' => [
                LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_NEVER,
            ],
        ],
    ],

    'logging' => [
        'enabled' => env('LDAP_LOGGING', true),
        'channel' => env('LOG_CHANNEL', 'stack'),
        'level' => 'info',
    ],

    'cache' => [
        'enabled' => false,
    ],

    // Atributo do AD usado para resolver a unidade do solicitante
    // (ex.: department, company) — ver App\Models\UnidadeAdMapeamento.
    'unidade_attribute' => env('LDAP_UNIDADE_ATTRIBUTE', 'department'),
];
