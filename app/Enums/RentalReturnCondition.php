<?php

namespace App\Enums;

enum RentalReturnCondition: string
{
    case GOOD = 'good';
    case DAMAGED = 'damaged';
    case LOST = 'lost';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
