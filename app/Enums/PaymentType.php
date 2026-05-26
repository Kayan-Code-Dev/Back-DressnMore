<?php

namespace App\Enums;

enum PaymentType: string
{
    case INVOICE_PAYMENT = 'invoice_payment';
    case SUPPLIER_PAYMENT = 'supplier_payment';
    case SECURITY_DEPOSIT_DEDUCTION = 'security_deposit_deduction';
    case MANUAL_ADJUSTMENT = 'manual_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::INVOICE_PAYMENT => 'Invoice Payment',
            self::SUPPLIER_PAYMENT => 'Supplier Payment',
            self::SECURITY_DEPOSIT_DEDUCTION => 'Security Deposit Deduction',
            self::MANUAL_ADJUSTMENT => 'Manual Adjustment',
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
