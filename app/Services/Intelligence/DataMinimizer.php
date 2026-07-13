<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

/**
 * Strips PII and sensitive data before sending tool results to external providers.
 */
final class DataMinimizer
{
    private static array $piiKeys = [
        'phone', 'mobile', 'email', 'address', 'national_id', 'password',
        'token', 'secret', 'note', 'notes', 'private', 'internal',
        'created_by', 'updated_by', 'deleted_at',
    ];

    /**
     * Minimize tool facts before sending to external model.
     */
    public static function minimize(array $facts): array
    {
        return self::stripPii($facts);
    }

    private static function stripPii(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);
            if (self::isPiiKey($lowerKey)) {
                continue;
            }
            if (is_array($value)) {
                $result[$key] = self::stripPii($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    private static function isPiiKey(string $key): bool
    {
        foreach (self::$piiKeys as $pii) {
            if (str_contains($key, $pii)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Minimize customer references — replace names with anonymous refs.
     */
    public static function anonymizeCustomers(array $items): array
    {
        $anonMap = [];
        $counter = 1;
        $result = [];

        foreach ($items as $item) {
            $anonItem = $item;
            if (isset($item['customer']) && is_string($item['customer'])) {
                $original = $item['customer'];
                if (!isset($anonMap[$original])) {
                    $anonMap[$original] = "عميل_{$counter}";
                    $counter++;
                }
                $anonItem['customer'] = $anonMap[$original];
            }
            $result[] = $anonItem;
        }

        return ['items' => $result, 'alias_map' => $anonMap];
    }

    /**
     * Restore real names from alias map for final response (if authorized).
     */
    public static function deanonymize(string $text, array $aliasMap): string
    {
        foreach ($aliasMap as $real => $alias) {
            $text = str_replace($alias, $real, $text);
        }
        return $text;
    }
}
