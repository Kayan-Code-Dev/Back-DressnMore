<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Settings\DeleteAccountRequest;
use App\Http\Requests\Tenant\Settings\UpdatePasswordRequest;
use App\Http\Requests\Tenant\Settings\UpdateProfileRequest;
use App\Http\Requests\Tenant\Settings\UploadAvatarRequest;
use App\Http\Resources\Tenant\UserResource;
use App\Services\Tenant\TenantContext;
use App\Services\Tenant\TenantUserAvatarService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantUserAvatarService $avatarService,
    ) {}

    public function profile(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()));
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        return ApiResponse::success(new UserResource($user->refresh()), 'Profile updated');
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $tenant = $this->tenantContext->requireTenant();
        $user = $request->user();
        $this->avatarService->assertTenantContext($tenant);

        $previousPath = $user->avatar_path;
        $storedPath = $this->avatarService->store($tenant, $user, $request->file('avatar'));

        $user->avatar_path = $storedPath;
        $user->save();

        $this->avatarService->deleteIfOwned($tenant, $previousPath);

        return ApiResponse::success(new UserResource($user->refresh()), 'Avatar updated');
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), (string) $user->password)) {
            return ApiResponse::error('Current password is incorrect', 422);
        }

        $user->password = $request->string('password')->toString();
        $user->save();

        return ApiResponse::success(null, 'Password updated');
    }

    public function deleteAccount(DeleteAccountRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('password')->toString(), (string) $user->password)) {
            return ApiResponse::error('Password is incorrect', 422);
        }

        $tenant = $this->tenantContext->tenant();
        if ($tenant !== null) {
            $this->avatarService->deleteIfOwned($tenant, $user->avatar_path);
        }

        $user->tokens()->delete();
        $user->status = 'deleted';
        $user->email = 'deleted+'.$user->id.'+'.Str::random(8).'@deleted.local';
        $user->name = 'Deleted User';
        $user->avatar_path = null;
        $user->save();

        return ApiResponse::success(null, 'Account deleted');
    }
}
