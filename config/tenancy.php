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
];
