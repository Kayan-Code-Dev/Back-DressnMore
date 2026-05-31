<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_code' => sprintf('CUS-%03d', $this->id),
            'name' => $this->name,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'wedding_date' => $this->date_of_birth?->toDateString(),
            'visit_date' => $this->visit_date?->toDateString(),
            'phone' => $this->phone,
            'phone2' => $this->phone2,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'address' => $this->address,
            'city_id' => $this->city_id,
            'city_name' => null,
            'national_id' => $this->national_id,
            'source' => $this->source,
            'notes' => $this->notes,
            'status' => $this->status,
            'is_vip' => (bool) ($this->notes && str_contains((string) $this->notes, '[VIP]')),
            'orders_count' => (int) ($this->orders_count ?? 0),
            'total_spent' => round((float) ($this->total_spent ?? 0), 2),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
