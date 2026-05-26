<?php

namespace App\Enums;

enum InvoiceType: string
{
    case RENT = 'rent';
    case SELL = 'sell';
    case TAILORING = 'tailoring';

    public function label(): string
    {
        return match ($this) {
            self::RENT => 'Rent',
            self::SELL => 'Sell',
            self::TAILORING => 'Tailoring',
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
