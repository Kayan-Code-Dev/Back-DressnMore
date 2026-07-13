<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use Illuminate\Support\Facades\Log;

/**
 * Validates that model responses contain only numbers from tool results.
 */
final class NumericalIntegrityValidator
{
    /**
     * Extract all numbers from text.
     * @return array<int|float>
     */
    public static function extractNumbers(string $text): array
    {
        preg_match_all('/[\d,]+(?:\.\d+)?/', $text, $matches);
        $numbers = [];
        foreach ($matches[0] as $match) {
            $clean = str_replace(',', '', $match);
            if (str_contains($clean, '.')) {
                $numbers[] = (float) $clean;
            } else {
                $numbers[] = (int) $clean;
            }
        }
        return $numbers;
    }

    /**
     * Extract all numbers from nested facts array.
     * @return array<int|float>
     */
    public static function extractNumbersFromFacts(array $facts): array
    {
        $numbers = [];
        self::collectNumbers($facts, $numbers);
        return array_unique($numbers);
    }

    private static function collectNumbers(mixed $data, array &$out): void
    {
        if (is_numeric($data)) {
            $out[] = is_float($data) ? $data : (int) $data;
        } elseif (is_array($data)) {
            foreach ($data as $v) {
                self::collectNumbers($v, $out);
            }
        }
    }

    /**
     * Check if all numbers in the response exist in the tool facts.
     * Returns true if response passes integrity check.
     */
    public static function validate(string $response, array $toolFacts): bool
    {
        $responseNumbers = self::extractNumbers($response);
        if ($responseNumbers === []) {
            return true; // No numbers to validate
        }

        $factNumbers = self::extractNumbersFromFacts($toolFacts);

        foreach ($responseNumbers as $num) {
            $found = false;
            foreach ($factNumbers as $factNum) {
                // Allow exact match or match when divided by 1000 (for formatting differences)
                if ($num === $factNum || $num * 1000 === $factNum || $num === $factNum * 1000) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                Log::warning('Numerical integrity failure', [
                    'number' => $num,
                    'response_preview' => substr($response, 0, 200),
                ]);
                return false;
            }
        }

        return true;
    }
}
