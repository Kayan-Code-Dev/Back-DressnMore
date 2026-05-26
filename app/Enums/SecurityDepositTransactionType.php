<?php

namespace App\Enums;

enum SecurityDepositTransactionType: string
{
    case COLLECTED = 'collected';
    case DEDUCTED = 'deducted';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::COLLECTED => 'Collected',
            self::DEDUCTED => 'Deducted',
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
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases()
        );
    }
}
