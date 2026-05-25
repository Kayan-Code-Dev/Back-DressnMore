<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Branch;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Branch::query()->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $branch = Branch::query()->create($data);

        return ApiResponse::success($branch, 'Created', 201);
    }

    public function show(int $branch): JsonResponse
    {
        return ApiResponse::success(Branch::query()->findOrFail($branch));
    }

    public function update(Request $request, int $branch): JsonResponse
    {
        $branchModel = Branch::query()->findOrFail($branch);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $branchModel->update($data);

        return ApiResponse::success($branchModel->fresh(), 'Updated');
    }

    public function destroy(int $branch): JsonResponse
    {
        Branch::query()->findOrFail($branch)->delete();

        return ApiResponse::success(null, 'Deleted');
    }
}
