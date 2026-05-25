<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(User::query()->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:tenant.users,email'],
            'password' => ['required', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = User::query()->create($data);

        return ApiResponse::success($user, 'Created', 201);
    }

    public function show(int $user): JsonResponse
    {
        return ApiResponse::success(User::query()->findOrFail($user));
    }

    public function update(Request $request, int $user): JsonResponse
    {
        $userModel = User::query()->findOrFail($user);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:tenant.users,email,'.$userModel->id],
            'password' => ['sometimes', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $userModel->update($data);

        return ApiResponse::success($userModel->fresh(), 'Updated');
    }

    public function destroy(int $user): JsonResponse
    {
        User::query()->findOrFail($user)->delete();

        return ApiResponse::success(null, 'Deleted');
    }
}
