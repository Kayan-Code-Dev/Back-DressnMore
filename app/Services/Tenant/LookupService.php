<?php

namespace App\Services\Tenant;

use App\Enums\CashboxStatus;
use App\Enums\CashMovementDirection;
use App\Enums\CashMovementType;
use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\DeliveryRecordType;
use App\Enums\DressStatus;
use App\Enums\ExpenseStatus;
use App\Enums\ExpenseWorkflowStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReportPeriod;
use App\Enums\SecurityDepositStatus;
use App\Enums\SecurityDepositTransactionType;
use App\Enums\SupplierStatus;
use App\Enums\VatType;
use App\Models\Tenant\ProductTransfer;

class LookupService
{
    /**
     * @return array<string, list<array{value:string,label:string}>>
     */
    public function all(): array
    {
        return [
            'customer_statuses' => CustomerStatus::options(),
            'customer_sources' => CustomerSource::options(),
            'dress_statuses' => DressStatus::options(),
            'category_statuses' => CustomerStatus::options(),
            'invoice_types' => InvoiceType::options(),
            'invoice_statuses' => InvoiceStatus::options(),
            'payment_methods' => PaymentMethod::options(),
            'payment_statuses' => PaymentStatus::options(),
            'payment_types' => PaymentType::options(),
            'security_deposit_statuses' => SecurityDepositStatus::options(),
            'inventory_movement_types' => InventoryMovementType::options(),
            'delivery_record_types' => DeliveryRecordType::options(),
            'security_deposit_transaction_types' => SecurityDepositTransactionType::options(),
            'expense_statuses' => ExpenseWorkflowStatus::options(),
            'expense_category_statuses' => ExpenseStatus::options(),
            'branch_statuses' => CustomerStatus::options(),
            'vat_types' => VatType::options(),
            'cashbox_statuses' => CashboxStatus::options(),
            'supplier_statuses' => SupplierStatus::options(),
            'purchase_order_statuses' => PurchaseOrderStatus::options(),
            'report_periods' => ReportPeriod::options(),
            'cash_movement_types' => CashMovementType::options(),
            'cash_movement_directions' => CashMovementDirection::options(),
            'product_transfer_statuses' => collect(ProductTransfer::statuses())
                ->map(fn (string $status): array => ['value' => $status, 'label' => str_replace('_', ' ', ucfirst($status))])
                ->values()
                ->all(),
            'dress_status_after_return' => [
                ['value' => DressStatus::AVAILABLE->value, 'label' => DressStatus::AVAILABLE->label()],
                ['value' => DressStatus::MAINTENANCE->value, 'label' => DressStatus::MAINTENANCE->label()],
            ],
        ];
    }
}
