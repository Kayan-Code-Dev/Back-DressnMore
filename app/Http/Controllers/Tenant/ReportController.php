<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\ReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function overview(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->overview([
            'period' => $request->query('period'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'branch_id' => $request->query('branch_id'),
        ]));
    }

    public function sales(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->salesSummary([
            'period' => $request->query('period'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'branch_id' => $request->query('branch_id'),
        ]));
    }

    public function tailoring(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->tailoringSummary([
            'period' => $request->query('period'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'branch_id' => $request->query('branch_id'),
        ]));
    }
}
