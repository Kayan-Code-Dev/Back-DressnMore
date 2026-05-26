<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoicePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'customer_id' => $this->invoice?->customer_id,
            'client_id' => $this->invoice?->customer_id,
            'branch_id' => $this->invoice?->branch_id,
            'amount' => $this->amount,
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
