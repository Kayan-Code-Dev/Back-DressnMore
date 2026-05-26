<?php

namespace App\Enums;

enum DeliveryRecordType: string
{
    case DELIVERED = 'delivered';
    case RETURNED = 'returned';
    case CANCELLED_DELIVERY = 'cancelled_delivery';

    public function label(): string
    {
        return match ($this) {
            self::DELIVERED => 'Delivered',
            self::RETURNED => 'Returned',
            self::CANCELLED_DELIVERY => 'Cancelled Delivery',
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
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases()
        );
    }
}
