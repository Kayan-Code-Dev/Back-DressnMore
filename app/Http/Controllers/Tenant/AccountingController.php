<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\AccountingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    public function __construct(private readonly AccountingService $accountingService) {}

    public function summary(Request $request): JsonResponse
    {
        return ApiResponse::success($this->accountingService->summary([
            'period' => $request->query('period'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'branch_id' => $request->query('branch_id'),
        ]));
    }

    public function ledger(Request $request): JsonResponse
    {
        return ApiResponse::success($this->accountingService->ledger([
            'period' => $request->query('period'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'branch_id' => $request->query('branch_id'),
            'search' => $request->query('search'),
        ]));
    }
}
