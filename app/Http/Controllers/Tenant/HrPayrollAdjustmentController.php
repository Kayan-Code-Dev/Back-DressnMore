<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Hr\Payroll\StoreHrPayrollAdjustmentRequest;
use App\Models\Tenant\HrPayrollAdjustment;
use App\Services\Tenant\HrPayrollAdjustmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrPayrollAdjustmentController extends Controller
{
    public function __construct(private readonly HrPayrollAdjustmentService $adjustmentService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $paginator = $this->adjustmentService->paginate([
            'type' => $request->query('type'),
            'employee_id' => $request->query('employee_id'),
            'month' => $request->query('month'),
        ], $perPage);

        $rows = collect($paginator->items())
            ->map(fn (HrPayrollAdjustment $row) => $this->adjustmentService->present($row))
            ->all();

        return ApiResponse::paginated($paginator, $rows);
    }

    public function store(StoreHrPayrollAdjustmentRequest $request): JsonResponse
    {
        $adjustment = $this->adjustmentService->create($request->validated());

        return ApiResponse::success(
            $this->adjustmentService->present($adjustment->load('employee')),
            'Adjustment created',
            201,
        );
    }

    public function destroy(HrPayrollAdjustment $adjustment): JsonResponse
    {
        $this->adjustmentService->delete($adjustment);

        return ApiResponse::success(null, 'Adjustment deleted');
    }
}
