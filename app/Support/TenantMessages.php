<?php

namespace App\Support;

final class TenantMessages
{
    public const CONTEXT_REQUIRED = 'Tenant context is required';

    public const NOT_FOUND = 'Tenant not found';

    public const TOKEN_MISMATCH = 'Token is not valid for this tenant';
}
