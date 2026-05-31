<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentGatewayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'accountHolder' => $this->account_holder,
            'accountNumber' => $this->account_number,
            'bankName' => $this->bank_name,
            'iban' => $this->iban,
            'instructions' => $this->instructions,
            'isActive' => (bool) $this->is_active,
            'displayOrder' => (int) $this->display_order,
            'createdAt' => $this->created_at?->toDateString(),
            'usageCount' => (int) $this->usage_count,
        ];
    }
}
