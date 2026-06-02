<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Hr\Employee\PatchHrEmployeeStatusRequest;
use App\Http\Requests\Tenant\Hr\Employee\StoreHrEmployeeRequest;
use App\Http\Requests\Tenant\Hr\Employee\UpdateHrEmployeeRequest;
use App\Http\Resources\Tenant\HrDocumentResource;
use App\Http\Resources\Tenant\HrEmployeeResource;
use App\Http\Resources\Tenant\HrEmployeeSummaryResource;
use App\Services\Tenant\HrDocumentService;
use App\Services\Tenant\HrEmployeeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrEmployeeController extends Controller
{
    public function __construct(
        private readonly HrEmployeeService $hrEmployeeService,
        private readonly HrDocumentService $hrDocumentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $employees = $this->hrEmployeeService->paginate([
            'search' => $request->query('search'),
            'branch_id' => $request->query('branch_id'),
            'department_id' => $request->query('department_id'),
            'job_title_id' => $request->query('job_title_id'),
            'status' => $request->query('status'),
            'employment_type' => $request->query('employment_type'),
        ], $perPage);

        return ApiResponse::paginated($employees, HrEmployeeResource::collection($employees->items())->resolve());
    }

    public function store(StoreHrEmployeeRequest $request): JsonResponse
    {
        $employee = $this->hrEmployeeService->create($request->validated());

        return ApiResponse::success(new HrEmployeeResource($employee), 'Employee created', 201);
    }

    public function show(int $employee): JsonResponse
    {
        return ApiResponse::success(new HrEmployeeResource($this->hrEmployeeService->findOrFail($employee)));
    }

    public function update(UpdateHrEmployeeRequest $request, int $employee): JsonResponse
    {
        $employeeModel = $this->hrEmployeeService->findOrFail($employee);
        $employeeModel = $this->hrEmployeeService->update($employeeModel, $request->validated());

        return ApiResponse::success(new HrEmployeeResource($employeeModel), 'Employee updated');
    }

    public function destroy(int $employee): JsonResponse
    {
        $employeeModel = $this->hrEmployeeService->findOrFail($employee);
        $this->hrEmployeeService->delete($employeeModel);

        return ApiResponse::success(null, 'Employee deleted');
    }

    public function updateStatus(PatchHrEmployeeStatusRequest $request, int $employee): JsonResponse
    {
        $employeeModel = $this->hrEmployeeService->findOrFail($employee);
        $employeeModel = $this->hrEmployeeService->updateStatus($employeeModel, $request->validated());

        return ApiResponse::success(new HrEmployeeResource($employeeModel), 'Employee status updated');
    }

    public function summary(int $employee): JsonResponse
    {
        $employeeModel = $this->hrEmployeeService->findOrFail($employee);
        $summary = $this->hrEmployeeService->buildSummary($employeeModel);

        return ApiResponse::success(new HrEmployeeSummaryResource($summary));
    }

    public function documents(Request $request, int $employee): JsonResponse
    {
        $this->hrEmployeeService->findOrFail($employee);
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $documents = $this->hrDocumentService->paginate([
            'employee_id' => $employee,
            'document_type' => $request->query('document_type'),
            'status' => $request->query('status'),
        ], $perPage);

        return ApiResponse::paginated($documents, HrDocumentResource::collection($documents->items())->resolve());
    }
}
