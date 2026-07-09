<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Setting;

class TenantSettingsService
{
    /** @var array<string, array<string, mixed>> */
    private const DEFAULTS = [
        'app' => [
            'timezone' => 'Africa/Cairo',
            'currency' => 'EGP',
        ],
        'invoice' => [
            'tax_rate' => 14,
            'invoice_prefix' => 'INV',
        ],
        'rental' => [
            'late_fee_per_day' => 50,
        ],
        'company' => [
            'name' => null,
            'phone' => null,
            'email' => null,
            'address' => null,
        ],
    ];

    /** @var array<string, string> */
    private const KEY_MAP = [
        'app' => 'app.settings',
        'invoice' => 'invoice.defaults',
        'rental' => 'rental.policy',
        'company' => 'company.profile',
    ];

    /**
     * Get all tenant settings merged with defaults.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $result = [];

        foreach (self::KEY_MAP as $group => $dbKey) {
            $saved = Setting::query()->where('key', $dbKey)->first();
            $savedValue = $saved ? (is_string($saved->value) ? json_decode($saved->value, true) : $saved->value) : [];
            $result[$group] = array_merge(self::DEFAULTS[$group] ?? [], $savedValue ?: []);
        }

        return $result;
    }

    /**
     * Update tenant settings. Only provided groups are updated.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(array $data): array
    {
        foreach ($data as $group => $values) {
            if (! is_array($values) || ! isset(self::KEY_MAP[$group])) {
                continue;
            }

            $dbKey = self::KEY_MAP[$group];
            $existing = Setting::query()->where('key', $dbKey)->first();
            $existingValue = $existing ? (is_string($existing->value) ? json_decode($existing->value, true) : $existing->value) : [];

            // Merge with existing, filtering to allowed keys only
            $allowedKeys = array_keys(self::DEFAULTS[$group] ?? []);
            $filteredValues = array_intersect_key($values, array_flip($allowedKeys));
            $mergedValue = array_merge($existingValue ?: [], $filteredValues);

            Setting::query()->updateOrCreate(
                ['key' => $dbKey],
                ['value' => $mergedValue]
            );
        }

        return $this->all();
    }

    /**
     * Get a single setting group.
     *
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        if (! isset(self::KEY_MAP[$group])) {
            return [];
        }

        $saved = Setting::query()->where('key', self::KEY_MAP[$group])->first();
        $savedValue = $saved ? (is_string($saved->value) ? json_decode($saved->value, true) : $saved->value) : [];

        return array_merge(self::DEFAULTS[$group] ?? [], $savedValue ?: []);
    }

    /**
     * Get a single setting value with default fallback.
     */
    public function value(string $group, string $key, mixed $default = null): mixed
    {
        $groupData = $this->group($group);

        return $groupData[$key] ?? $default;
    }
}
