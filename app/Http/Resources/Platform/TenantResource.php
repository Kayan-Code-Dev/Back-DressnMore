<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'database_name' => $this->database_name,
            'status' => $this->status,
            'owner_name' => $this->owner_name,
            'owner_email' => $this->owner_email,
            'created_at' => $this->created_at,
        ];
    }
}
