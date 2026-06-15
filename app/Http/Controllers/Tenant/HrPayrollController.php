<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\HrEmployee;
use App\Services\Tenant\HrPayrollService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrPayrollController extends Controller
{
    public function __construct(private readonly HrPayrollService $payrollService) {}

    public function index(Request $request): JsonResponse
    {
        $month = (string) ($request->query('month') ?: now()->format('Y-m'));
        $branchId = $request->integer('branch_id') ?: null;

        return ApiResponse::success($this->payrollService->payrollForMonth($month, $branchId));
    }

    public function payslip(Request $request, int $employee): JsonResponse
    {
        $month = (string) ($request->query('month') ?: now()->format('Y-m'));
        $employeeModel = HrEmployee::query()
            ->with(['branch', 'department', 'jobTitle'])
            ->findOrFail($employee);

        return ApiResponse::success($this->payrollService->payslipForEmployee($employeeModel, $month));
    }
}
