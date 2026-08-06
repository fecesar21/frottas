<?php

return [
    'default' => env('LDAP_CONNECTION', 'default'),

    'connections' => [
        'default' => [
            'hosts'    => [env('LDAP_HOST', '')],
            'username' => env('LDAP_USERNAME', ''),
            'password' => env('LDAP_PASSWORD', ''),
            'port'     => env('LDAP_PORT', 636),
            'base_dn'  => env('LDAP_BASE_DN', ''),
            'timeout'  => env('LDAP_TIMEOUT', 5),
            'use_tls'  => env('LDAP_USE_SSL', true),
            'use_starttls' => env('LDAP_USE_TLS', false),
        ],
    ],

    'logging' => [
        'enabled'  => env('LDAP_LOGGING', true),
        'channel'  => env('LOG_CHANNEL', 'stack'),
        'level'    => 'info',
    ],

    'cache' => [
        'enabled' => false,
    ],

    // Atributo do AD usado para resolver a unidade do solicitante
    // (ex.: department, company) — ver App\Models\UnidadeAdMapeamento.
    'unidade_attribute' => env('LDAP_UNIDADE_ATTRIBUTE', 'department'),
];
