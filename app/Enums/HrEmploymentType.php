<?php

namespace App\Enums;

enum HrEmploymentType: string
{
    case FULL_TIME = 'full_time';
    case PART_TIME = 'part_time';
    case CONTRACTOR = 'contractor';
    case TEMPORARY = 'temporary';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
