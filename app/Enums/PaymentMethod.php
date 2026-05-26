<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case INSTAPAY = 'instapay';
    case VODAFONE_CASH = 'vodafone_cash';
    case BANK_TRANSFER = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::INSTAPAY => 'InstaPay',
            self::VODAFONE_CASH => 'Vodafone Cash',
            self::BANK_TRANSFER => 'Bank Transfer',
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
            fn (self $method): array => ['value' => $method->value, 'label' => $method->label()],
            self::cases()
        );
    }
}
