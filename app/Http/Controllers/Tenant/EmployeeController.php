<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\EmployeeService;
use App\Support\ApiResponse;
use App\Support\Tenant\HrOperationsPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $employeeService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $employees = $this->employeeService->paginate(['search' => $request->query('search')], $perPage);
        $rows = collect($employees->items())->map(fn ($employee) => HrOperationsPresenter::employee($employee))->all();

        return ApiResponse::success($rows, 'Success', 200, [
            'current_page' => $employees->currentPage(),
            'per_page' => $employees->perPage(),
            'total' => $employees->total(),
            'last_page' => $employees->lastPage(),
            'stats' => $this->employeeService->stats(['search' => $request->query('search')]),
        ]);
    }

    public function show(int $employee): JsonResponse
    {
        return ApiResponse::success(HrOperationsPresenter::employee($this->employeeService->findOrFail($employee)));
    }

    public function custodies(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $rows = $this->employeeService->paginateCustodies(['search' => $request->query('search')], $perPage);
        $data = collect($rows->items())->map(fn ($row) => HrOperationsPresenter::custody($row))->all();

        return ApiResponse::paginated($rows, $data);
    }

    public function salaries(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $rows = $this->employeeService->paginateSalaries(['search' => $request->query('search')], $perPage);
        $data = collect($rows->items())->map(fn ($row) => HrOperationsPresenter::salary($row))->all();

        return ApiResponse::success($data, 'Success', 200, [
            'current_page' => $rows->currentPage(),
            'per_page' => $rows->perPage(),
            'total' => $rows->total(),
            'last_page' => $rows->lastPage(),
            'stats' => $this->employeeService->salaryStats(['search' => $request->query('search')]),
        ]);
    }
}
