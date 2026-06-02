<?php

namespace App\Enums;

enum HrDocumentStatus: string
{
    case VALID = 'valid';
    case EXPIRING_SOON = 'expiring_soon';
    case EXPIRED = 'expired';
    case MISSING = 'missing';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
