<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Customer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Customer::query()->paginate(20));
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

        $customer = Customer::query()->create($data);

        return ApiResponse::success($customer, 'Created', 201);
    }

    public function show(int $customer): JsonResponse
    {
        return ApiResponse::success(Customer::query()->findOrFail($customer));
    }

    public function update(Request $request, int $customer): JsonResponse
    {
        $customerModel = Customer::query()->findOrFail($customer);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $customerModel->update($data);

        return ApiResponse::success($customerModel->fresh(), 'Updated');
    }

    public function destroy(int $customer): JsonResponse
    {
        Customer::query()->findOrFail($customer)->delete();

        return ApiResponse::success(null, 'Deleted');
    }
}
