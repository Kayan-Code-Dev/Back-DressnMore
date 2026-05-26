<?php

namespace App\Enums;

enum VatType: string
{
    case FIXED = 'fixed';
    case PERCENTAGE = 'percentage';

    public function label(): string
    {
        return match ($this) {
            self::FIXED => 'Fixed',
            self::PERCENTAGE => 'Percentage',
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
