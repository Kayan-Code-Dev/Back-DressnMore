<?php

namespace App\Services\Tenant;

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

        return $this->resolveSlugFromHost($request->getHost());
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
