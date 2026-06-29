<?php

namespace App\Http\Resources\Tenant;

use App\Enums\PaymentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoicePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => 'PAY-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT),
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice?->invoice_number ?? ($this->payment_type === PaymentType::MANUAL_ADJUSTMENT->value ? 'دفعة عامة' : ''),
            'customer_id' => $this->invoice?->customer_id,
            'client_id' => $this->invoice?->customer_id,
            'customer_name' => $this->invoice?->customer?->name ?? ($this->payment_type === PaymentType::MANUAL_ADJUSTMENT->value ? 'دفعة عامة' : ''),
            'branch_id' => $this->branch_id ?? $this->invoice?->branch_id,
            'branch_name' => $this->branch?->name ?? $this->invoice?->branch?->name ?? '',
            'cashbox_id' => $this->cashbox_id,
            'cashbox_name' => $this->cashbox?->name ?? '',
            'amount' => round((float) $this->amount, 2),
            'status' => $this->status,
            'payment_type' => $this->payment_type,
            'method' => $this->method,
            'reference' => $this->reference,
            'paid_at' => $this->paid_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
