<?php

namespace App\Enums;

enum TailoringProductionStage: string
{
    case NEW_ORDER = 'new_order';
    case MEASUREMENTS_TAKEN = 'measurements_taken';
    case FABRIC_CUTTING = 'fabric_cutting';
    case SEWING = 'sewing';
    case FIRST_FITTING = 'first_fitting';
    case ADJUSTMENTS = 'adjustments';
    case FINAL_FITTING = 'final_fitting';
    case READY_FOR_DELIVERY = 'ready_for_delivery';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function ordered(): array
    {
        return [
            self::NEW_ORDER->value,
            self::MEASUREMENTS_TAKEN->value,
            self::FABRIC_CUTTING->value,
            self::SEWING->value,
            self::FIRST_FITTING->value,
            self::ADJUSTMENTS->value,
            self::FINAL_FITTING->value,
            self::READY_FOR_DELIVERY->value,
            self::DELIVERED->value,
        ];
    }

    public function index(): int
    {
        $ordered = self::ordered();
        $index = array_search($this->value, $ordered, true);

        return $index === false ? 0 : (int) $index;
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::NEW_ORDER => 'طلب جديد',
            self::MEASUREMENTS_TAKEN => 'تم أخذ المقاسات',
            self::FABRIC_CUTTING => 'قص القماش',
            self::SEWING => 'خياطة',
            self::FIRST_FITTING => 'بروفة أولى',
            self::ADJUSTMENTS => 'تعديلات',
            self::FINAL_FITTING => 'بروفة نهائية',
            self::READY_FOR_DELIVERY => 'جاهز للتسليم',
            self::DELIVERED => 'تم التسليم',
            self::CANCELLED => 'ملغي',
        };
    }
}
