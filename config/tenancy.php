<?php

return [
    'tenant_statuses' => [
        'provisioning',
        'active',
        'suspended',
        'expired',
        'provisioning_failed',
    ],

    'provisioning' => [
        'database_prefix' => env('TENANT_DATABASE_PREFIX', 'tenant_'),
        'database_suffix' => env('TENANT_DATABASE_SUFFIX', ''),
    ],

    'domain' => [
        'base_domains' => array_values(array_filter(array_map(
            static fn (string $domain): string => trim($domain),
            explode(',', (string) env('TENANT_BASE_DOMAINS', 'dressnmore.it.com,localhost'))
        ))),
    ],
];
