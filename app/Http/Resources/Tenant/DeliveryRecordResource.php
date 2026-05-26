<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'type' => $this->type,
            'delivered_at' => $this->delivered_at?->toISOString(),
            'returned_at' => $this->returned_at?->toISOString(),
            'receiver_name' => $this->receiver_name,
            'receiver_phone' => $this->receiver_phone,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
