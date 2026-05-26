<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case CREATED = 'created';
    case STATUS_CHANGED = 'status_changed';
    case MAINTENANCE = 'maintenance';
    case SOLD = 'sold';
    case RENTED = 'rented';
    case RETURNED = 'returned';
    case MANUAL_ADJUSTMENT = 'manual_adjustment';
    case BRANCH_TRANSFER = 'branch_transfer';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Created',
            self::STATUS_CHANGED => 'Status Changed',
            self::MAINTENANCE => 'Maintenance',
            self::SOLD => 'Sold',
            self::RENTED => 'Rented',
            self::RETURNED => 'Returned',
            self::MANUAL_ADJUSTMENT => 'Manual Adjustment',
            self::BRANCH_TRANSFER => 'Branch Transfer',
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
