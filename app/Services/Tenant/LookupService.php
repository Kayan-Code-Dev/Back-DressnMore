<?php

namespace App\Services\Tenant;

use App\Enums\CustomerStatus;
use App\Enums\DeliveryRecordType;
use App\Enums\DressStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Enums\SecurityDepositStatus;
use App\Enums\SecurityDepositTransactionType;

class LookupService
{
    /**
     * @return array<string, list<array{value:string,label:string}>>
     */
    public function all(): array
    {
        return [
            'customer_statuses' => CustomerStatus::options(),
            'dress_statuses' => DressStatus::options(),
            'category_statuses' => CustomerStatus::options(),
            'invoice_types' => InvoiceType::options(),
            'invoice_statuses' => InvoiceStatus::options(),
            'payment_methods' => PaymentMethod::options(),
            'security_deposit_statuses' => SecurityDepositStatus::options(),
            'inventory_movement_types' => InventoryMovementType::options(),
            'delivery_record_types' => DeliveryRecordType::options(),
            'security_deposit_transaction_types' => SecurityDepositTransactionType::options(),
            'dress_status_after_return' => [
                ['value' => DressStatus::AVAILABLE->value, 'label' => DressStatus::AVAILABLE->label()],
                ['value' => DressStatus::MAINTENANCE->value, 'label' => DressStatus::MAINTENANCE->label()],
            ],
        ];
    }
}
