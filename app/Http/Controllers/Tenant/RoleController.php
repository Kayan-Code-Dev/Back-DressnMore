<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Role;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Role::query()->with('permissions')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:tenant.roles,name'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'exists:tenant.permissions,id'],
        ]);

        $role = Role::query()->create($data);
        $role->permissions()->sync($data['permission_ids'] ?? []);

        return ApiResponse::success($role->load('permissions'), 'Created', 201);
    }

    public function show(int $role): JsonResponse
    {
        return ApiResponse::success(Role::query()->with('permissions')->findOrFail($role));
    }

    public function update(Request $request, int $role): JsonResponse
    {
        $roleModel = Role::query()->findOrFail($role);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:tenant.roles,name,'.$roleModel->id],
            'display_name' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'exists:tenant.permissions,id'],
        ]);

        $roleModel->update($data);
        if (array_key_exists('permission_ids', $data)) {
            $roleModel->permissions()->sync($data['permission_ids']);
        }

        return ApiResponse::success($roleModel->fresh()->load('permissions'), 'Updated');
    }

    public function destroy(int $role): JsonResponse
    {
        Role::query()->findOrFail($role)->delete();

        return ApiResponse::success(null, 'Deleted');
    }
}
