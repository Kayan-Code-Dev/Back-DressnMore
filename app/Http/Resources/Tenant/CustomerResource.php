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
            'name' => $this->name,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'phone' => $this->phone,
            'phone2' => $this->phone2,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'address' => $this->address,
            'city_id' => $this->city_id,
            'national_id' => $this->national_id,
            'source' => $this->source,
            'notes' => $this->notes,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
