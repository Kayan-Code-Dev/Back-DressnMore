<?php

namespace App\Services\Tenant;

use App\Enums\CustomerStatus;
use App\Enums\DressStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Enums\SecurityDepositStatus;

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
        ];
    }
}
