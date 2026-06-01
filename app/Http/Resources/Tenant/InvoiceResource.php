<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'customer_id' => $this->customer_id,
            'client_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'type' => $this->type,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'tax' => $this->tax,
            'total' => $this->total,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'rent_start_date' => $this->rent_start_date?->toDateString(),
            'rent_end_date' => $this->rent_end_date?->toDateString(),
            'delivery_date' => $this->delivery_date?->toDateString(),
            'return_date' => $this->return_date?->toDateString(),
            'security_deposit' => $this->security_deposit,
            'security_deposit_status' => $this->security_deposit_status,
            'deposit_paid_amount' => $this->deposit_paid_amount,
            'tailoring_due_date' => $this->tailoring_due_date?->toDateString(),
            'visit_datetime' => $this->visit_datetime?->toISOString(),
            'occasion_datetime' => $this->occasion_datetime?->toISOString(),
            'days_of_rent' => $this->days_of_rent,
            'tailoring_notes' => $this->tailoring_notes,
            'notes' => $this->notes,
            'order_notes' => $this->order_notes,
            'created_by' => $this->created_by,
            'branch' => $this->whenLoaded('branch', fn () => new BranchResource($this->branch)),
            'items' => $this->whenLoaded('items', fn () => InvoiceItemResource::collection($this->items)),
            'payments' => $this->whenLoaded('payments', fn () => InvoicePaymentResource::collection($this->payments)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
