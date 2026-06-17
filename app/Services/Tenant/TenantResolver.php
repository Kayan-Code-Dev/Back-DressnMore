<?php

namespace App\Services\Tenant;

use App\Models\Central\PersonalAccessToken;
use App\Models\Central\Tenant;
use Illuminate\Http\Request;

class TenantResolver
{
    public function resolveSlug(Request $request): ?string
    {
        $header = $request->header('X-Tenant');
        if (is_string($header) && trim($header) !== '') {
            return trim($header);
        }

        $query = $request->query('tenant');
        if (is_string($query) && trim($query) !== '') {
            return trim($query);
        }

        $fromHost = $this->resolveSlugFromHost($request->getHost());
        if ($fromHost !== null) {
            return $fromHost;
        }

        return $this->resolveSlugFromBearerToken($request);
    }

    /**
     * Fallback resolution from the authenticated bearer token.
     *
     * Tokens are bound to a tenant on issue, so when no explicit tenant
     * context is supplied (header/query/subdomain) we derive it from the
     * token itself. This keeps the API usable for token-based SPA clients
     * that do not send an X-Tenant header.
     */
    private function resolveSlugFromBearerToken(Request $request): ?string
    {
        $bearer = $request->bearerToken();
        if (! is_string($bearer) || trim($bearer) === '') {
            return null;
        }

        $token = PersonalAccessToken::findToken($bearer);
        if ($token === null || $token->tenant_id === null) {
            return null;
        }

        $tenant = Tenant::query()->find($token->tenant_id);

        return $tenant?->slug;
    }

    private function resolveSlugFromHost(string $host): ?string
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return null;
        }

        /** @var list<string> $baseDomains */
        $baseDomains = config('tenancy.domain.base_domains', []);

        foreach ($baseDomains as $baseDomain) {
            $baseDomain = strtolower(trim($baseDomain));
            if ($baseDomain === '' || ! str_ends_with($host, $baseDomain)) {
                continue;
            }

            $prefix = substr($host, 0, -strlen($baseDomain));
            $prefix = rtrim($prefix, '.');

            if ($prefix === '') {
                continue;
            }

            $labels = explode('.', $prefix);
            $slug = end($labels);

            if (! is_string($slug) || $slug === '' || in_array($slug, ['www', 'api', 'staging-api', 'staging-tenant'], true)) {
                continue;
            }

            return $slug;
        }

        return null;
    }
}
