<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\HrDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrDashboardController extends Controller
{
    public function __construct(private readonly HrDashboardService $hrDashboardService) {}

    public function index(Request $request): JsonResponse
    {
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;

        return ApiResponse::success($this->hrDashboardService->build($branchId));
    }
}
