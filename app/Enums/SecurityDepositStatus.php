<?php

namespace App\Enums;

enum SecurityDepositStatus: string
{
    case PENDING = 'pending';
    case HELD = 'held';
    case RETURNED = 'returned';
    case FORFEITED = 'forfeited';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::HELD => 'Held',
            self::RETURNED => 'Returned',
            self::FORFEITED => 'Forfeited',
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
