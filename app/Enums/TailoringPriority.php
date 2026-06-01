<?php

namespace App\Enums;

enum TailoringPriority: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::LOW => 'منخفضة',
            self::NORMAL => 'عادية',
            self::HIGH => 'عالية',
            self::URGENT => 'عاجلة',
        };
    }
}
