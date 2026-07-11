<?php

namespace App\Http\Resources\Tenant\Intelligence;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'tokens_used' => $this->tokens_used,
            'generation_time_ms' => $this->generation_time_ms,
            'assistant_message' => $this->whenLoaded('assistantMessage', function () {
                return new MessageResource($this->assistantMessage);
            }),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
