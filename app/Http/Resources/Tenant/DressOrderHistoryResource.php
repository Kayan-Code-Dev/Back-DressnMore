<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DressOrderHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice?->invoice_number,
            'invoice_type' => $this->invoice?->type,
            'invoice_status' => $this->invoice?->status,
            'customer_id' => $this->invoice?->customer_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total' => $this->total,
            'rent_start_date' => $this->invoice?->rent_start_date?->toDateString(),
            'rent_end_date' => $this->invoice?->rent_end_date?->toDateString(),
            'delivery_date' => $this->invoice?->delivery_date?->toDateString(),
            'return_date' => $this->invoice?->return_date?->toDateString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
