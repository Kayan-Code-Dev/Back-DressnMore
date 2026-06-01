<?php

namespace App\Enums;

enum RentalReturnSettlementStatus: string
{
    case PREVIEW = 'preview';
    case PENDING = 'pending';
    case SETTLED = 'settled';
    case WAIVED = 'waived';
    case CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function blockingStatuses(): array
    {
        return [
            self::PENDING->value,
            self::SETTLED->value,
        ];
    }
}
