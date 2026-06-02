<?php

namespace App\Enums;

enum HrSalaryType: string
{
    case MONTHLY = 'monthly';
    case DAILY = 'daily';
    case HOURLY = 'hourly';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
