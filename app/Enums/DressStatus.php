<?php

namespace App\Enums;

enum DressStatus: string
{
    case AVAILABLE = 'available';
    case RENTED = 'rented';
    case SOLD = 'sold';
    case MAINTENANCE = 'maintenance';
    case UNAVAILABLE = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Available',
            self::RENTED => 'Rented',
            self::SOLD => 'Sold',
            self::MAINTENANCE => 'Maintenance',
            self::UNAVAILABLE => 'Unavailable',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases()
        );
    }
}
