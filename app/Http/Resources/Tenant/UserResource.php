<?php

namespace App\Http\Resources\Tenant;

use App\Services\Tenant\TenantContext;
use App\Services\Tenant\TenantUserAvatarService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tenant = app(TenantContext::class)->tenant();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'avatar_path' => $this->avatar_path,
            'avatar_url' => app(TenantUserAvatarService::class)->url($this->avatar_path, $tenant),
        ];
    }
}
