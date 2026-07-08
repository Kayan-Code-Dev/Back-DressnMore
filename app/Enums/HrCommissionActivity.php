<?php

namespace App\Enums;

enum HrCommissionActivity: string
{
    case SALE = 'sale';
    case RENT = 'rent';
    case TAILORING = 'tailoring';
    case ALL = 'all';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
