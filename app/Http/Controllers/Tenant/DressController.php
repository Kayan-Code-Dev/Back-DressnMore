<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Dress;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DressController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Dress::query()->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100', 'unique:tenant.dresses,code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'rental_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $dress = Dress::query()->create($data);

        return ApiResponse::success($dress, 'Created', 201);
    }

    public function show(int $dress): JsonResponse
    {
        return ApiResponse::success(Dress::query()->findOrFail($dress));
    }

    public function update(Request $request, int $dress): JsonResponse
    {
        $dressModel = Dress::query()->findOrFail($dress);

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:100', 'unique:tenant.dresses,code,'.$dressModel->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'rental_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $dressModel->update($data);

        return ApiResponse::success($dressModel->fresh(), 'Updated');
    }

    public function destroy(int $dress): JsonResponse
    {
        Dress::query()->findOrFail($dress)->delete();

        return ApiResponse::success(null, 'Deleted');
    }
}
