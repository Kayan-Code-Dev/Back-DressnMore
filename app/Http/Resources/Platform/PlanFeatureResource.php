<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanFeatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'feature_key' => $this->feature_key,
            'feature_value' => $this->feature_value,
            'value_type' => $this->value_type,
        ];
    }
}
