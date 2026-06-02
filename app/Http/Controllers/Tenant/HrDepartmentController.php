<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Hr\Department\StoreHrDepartmentRequest;
use App\Http\Requests\Tenant\Hr\Department\UpdateHrDepartmentRequest;
use App\Http\Resources\Tenant\HrDepartmentResource;
use App\Services\Tenant\HrDepartmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrDepartmentController extends Controller
{
    public function __construct(private readonly HrDepartmentService $hrDepartmentService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $departments = $this->hrDepartmentService->paginate(
            search: $request->query('search'),
            status: $request->query('status'),
            perPage: $perPage,
        );

        return ApiResponse::paginated($departments, HrDepartmentResource::collection($departments->items())->resolve());
    }

    public function store(StoreHrDepartmentRequest $request): JsonResponse
    {
        $department = $this->hrDepartmentService->create($request->validated());

        return ApiResponse::success(new HrDepartmentResource($department), 'Department created', 201);
    }

    public function show(int $department): JsonResponse
    {
        return ApiResponse::success(new HrDepartmentResource($this->hrDepartmentService->findOrFail($department)));
    }

    public function update(UpdateHrDepartmentRequest $request, int $department): JsonResponse
    {
        $departmentModel = $this->hrDepartmentService->findOrFail($department);
        $departmentModel = $this->hrDepartmentService->update($departmentModel, $request->validated());

        return ApiResponse::success(new HrDepartmentResource($departmentModel), 'Department updated');
    }

    public function destroy(int $department): JsonResponse
    {
        $this->hrDepartmentService->delete($this->hrDepartmentService->findOrFail($department));

        return ApiResponse::success(null, 'Department deleted');
    }
}
