<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RentalReturnSettlementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'expected_return_date' => $this->expected_return_date?->toDateString(),
            'actual_return_date' => $this->actual_return_date?->toDateString(),
            'condition' => $this->condition,
            'late_days' => $this->late_days,
            'late_fee' => $this->late_fee,
            'damage_fee' => $this->damage_fee,
            'cleaning_fee' => $this->cleaning_fee,
            'other_fee' => $this->other_fee,
            'total_fees' => $this->total_fees,
            'deposit_amount' => $this->deposit_amount,
            'deposit_paid_amount' => $this->deposit_paid_amount,
            'deposit_refund_amount' => $this->deposit_refund_amount,
            'deposit_withheld_amount' => $this->deposit_withheld_amount,
            'additional_amount_due' => $this->additional_amount_due,
            'settlement_total' => $this->settlement_total,
            'status' => $this->status,
            'notes' => $this->notes,
            'journal_entry_id' => $this->journal_entry_id,
            'settled_at' => $this->settled_at?->toISOString(),
            'invoice' => $this->whenLoaded('invoice', fn () => new InvoiceResource($this->invoice)),
        ];
    }
}
