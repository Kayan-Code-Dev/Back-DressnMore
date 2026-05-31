<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\DeliveryWorkflowService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryWorkflowController extends Controller
{
    public function __construct(private readonly DeliveryWorkflowService $deliveryWorkflowService) {}

    public function deliveries(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $rows = $this->deliveryWorkflowService->paginateDeliveries([
            'search' => $request->query('search'),
        ], $perPage);

        return ApiResponse::paginated($rows, $rows->items());
    }

    public function returns(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $rows = $this->deliveryWorkflowService->paginateReturns([
            'search' => $request->query('search'),
        ], $perPage);

        return ApiResponse::paginated($rows, $rows->items());
    }

    public function overdue(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $rows = $this->deliveryWorkflowService->paginateOverdueReturns([
            'search' => $request->query('search'),
        ], $perPage);

        return ApiResponse::paginated($rows, $rows->items());
    }

    public function search(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $rows = $this->deliveryWorkflowService->paginateDeliverySearch([
            'search' => $request->query('search'),
            'page' => $request->integer('page', 1),
        ], $perPage);

        return ApiResponse::paginated($rows, $rows->items());
    }
}
