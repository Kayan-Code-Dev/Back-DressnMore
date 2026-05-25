<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Employee;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Employee::query()->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:tenant.branches,id'],
            'user_id' => ['nullable', 'integer', 'exists:tenant.users,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $employee = Employee::query()->create($data);

        return ApiResponse::success($employee, 'Created', 201);
    }

    public function show(int $employee): JsonResponse
    {
        return ApiResponse::success(Employee::query()->findOrFail($employee));
    }

    public function update(Request $request, int $employee): JsonResponse
    {
        $employeeModel = Employee::query()->findOrFail($employee);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:tenant.branches,id'],
            'user_id' => ['nullable', 'integer', 'exists:tenant.users,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $employeeModel->update($data);

        return ApiResponse::success($employeeModel->fresh(), 'Updated');
    }

    public function destroy(int $employee): JsonResponse
    {
        Employee::query()->findOrFail($employee)->delete();

        return ApiResponse::success(null, 'Deleted');
    }
}
