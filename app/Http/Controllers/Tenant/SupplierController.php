<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Supplier;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Supplier::query()->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $supplier = Supplier::query()->create($data);

        return ApiResponse::success($supplier, 'Created', 201);
    }

    public function show(int $supplier): JsonResponse
    {
        return ApiResponse::success(Supplier::query()->findOrFail($supplier));
    }

    public function update(Request $request, int $supplier): JsonResponse
    {
        $supplierModel = Supplier::query()->findOrFail($supplier);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $supplierModel->update($data);

        return ApiResponse::success($supplierModel->fresh(), 'Updated');
    }

    public function destroy(int $supplier): JsonResponse
    {
        Supplier::query()->findOrFail($supplier)->delete();

        return ApiResponse::success(null, 'Deleted');
    }
}
