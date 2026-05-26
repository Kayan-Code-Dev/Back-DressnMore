<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PAID => 'Paid',
            self::CANCELLED => 'Cancelled',
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
