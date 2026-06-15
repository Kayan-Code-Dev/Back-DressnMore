<?php

namespace App\Support;

final class PlanCurrency
{
    public const SUPPORTED = ['EGP', 'SAR', 'USD', 'EUR', 'GBP'];

    public static function normalize(?string $currency): string
    {
        $code = strtoupper(trim((string) $currency));

        return in_array($code, self::SUPPORTED, true) ? $code : 'EGP';
    }

    public static function symbol(?string $currency): string
    {
        return match (self::normalize($currency)) {
            'EGP' => 'ج.م',
            'SAR' => 'ر.س',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => 'ج.م',
        };
    }

    public static function label(?string $currency): string
    {
        return match (self::normalize($currency)) {
            'EGP' => 'جنيه مصري',
            'SAR' => 'ريال سعودي',
            'USD' => 'دولار أمريكي',
            'EUR' => 'يورو',
            'GBP' => 'جنيه إسترليني',
            default => 'جنيه مصري',
        };
    }
}
