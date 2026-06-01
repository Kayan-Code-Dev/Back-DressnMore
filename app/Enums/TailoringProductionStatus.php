<?php

namespace App\Enums;

enum TailoringProductionStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case WAITING_CUSTOMER = 'waiting_customer';
    case READY = 'ready';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::PENDING => 'قيد الانتظار',
            self::IN_PROGRESS => 'قيد التنفيذ',
            self::WAITING_CUSTOMER => 'بانتظار العميل',
            self::READY => 'جاهز',
            self::COMPLETED => 'مكتمل',
            self::CANCELLED => 'ملغي',
        };
    }
}
