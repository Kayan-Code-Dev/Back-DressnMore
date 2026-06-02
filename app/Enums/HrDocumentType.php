<?php

namespace App\Enums;

enum HrDocumentType: string
{
    case NATIONAL_ID = 'national_id';
    case CONTRACT = 'contract';
    case CERTIFICATE = 'certificate';
    case MEDICAL = 'medical';
    case INSURANCE = 'insurance';
    case OTHER = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
