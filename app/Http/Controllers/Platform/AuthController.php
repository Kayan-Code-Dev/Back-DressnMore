<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\LoginRequest;
use App\Http\Resources\Platform\SuperAdminResource;
use App\Services\Auth\PlatformAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly PlatformAuthService $platformAuthService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->platformAuthService->login(
            $request->string('email')->toString(),
            $request->string('password')->toString()
        );

        return ApiResponse::success([
            'token' => $result['token'],
            'user' => new SuperAdminResource($result['admin']),
        ], 'Platform login successful');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'user' => new SuperAdminResource($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out');
    }
}
