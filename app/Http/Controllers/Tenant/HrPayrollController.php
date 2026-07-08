<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Hr\Payroll\PayHrPayrollRequest;
use App\Models\Tenant\HrEmployee;
use App\Services\Tenant\HrPayrollPaymentService;
use App\Services\Tenant\HrPayrollService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrPayrollController extends Controller
{
    public function __construct(
        private readonly HrPayrollService $payrollService,
        private readonly HrPayrollPaymentService $paymentService,
    ) {}

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

    public function employeeHistory(Request $request, int $employee): JsonResponse
    {
        $months = max(1, min(12, $request->integer('months', 6)));
        $employeeModel = HrEmployee::query()->findOrFail($employee);

        return ApiResponse::success($this->payrollService->payrollHistoryForEmployee($employeeModel, $months));
    }

    public function pay(PayHrPayrollRequest $request): JsonResponse
    {
        $result = $this->paymentService->pay(
            $request->validated(),
            $request->user()?->id,
        );

        return ApiResponse::success($result, 'تم صرف الراتب بنجاح');
    }
}
