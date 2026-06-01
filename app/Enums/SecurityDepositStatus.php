<?php

namespace App\Enums;

enum SecurityDepositStatus: string
{
    case NONE = 'none';
    case HELD = 'held';
    case PARTIALLY_HELD = 'partially_held';
    case PARTIALLY_DEDUCTED = 'partially_deducted';
    case FULLY_DEDUCTED = 'fully_deducted';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'None',
            self::HELD => 'Held',
            self::PARTIALLY_HELD => 'Partially Held',
            self::PARTIALLY_DEDUCTED => 'Partially Deducted',
            self::FULLY_DEDUCTED => 'Fully Deducted',
            self::REFUNDED => 'Refunded',
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
