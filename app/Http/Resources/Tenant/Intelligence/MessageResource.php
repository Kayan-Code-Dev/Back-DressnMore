<?php

namespace App\Http\Resources\Tenant\Intelligence;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'tokens_used' => $this->tokens_used,
            'generation_time_ms' => $this->generation_time_ms,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
