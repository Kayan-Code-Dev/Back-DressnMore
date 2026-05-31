<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\DeliveryWorkflowService;
use App\Services\Tenant\InvoiceDeliveryListService;
use App\Support\ApiResponse;
use App\Support\Tenant\InvoiceDeliveryPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryWorkflowController extends Controller
{
    public function __construct(
        private readonly DeliveryWorkflowService $deliveryWorkflowService,
        private readonly InvoiceDeliveryListService $invoiceDeliveryListService,
    ) {}

    public function invoiceDeliveries(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $orders = $this->invoiceDeliveryListService->paginate([
            'search' => $request->query('search'),
            'payment_status' => $request->query('payment_status'),
            'delivery_status' => $request->query('delivery_status'),
            'employee_id' => $request->query('employee_id'),
            'branch_id' => $request->query('branch_id'),
            'event_date_from' => $request->query('event_date_from'),
            'event_date_to' => $request->query('event_date_to'),
        ], $perPage);

        $rows = collect($orders->items())
            ->map(fn ($invoice) => InvoiceDeliveryPresenter::fromInvoice($invoice))
            ->values()
            ->all();

        return ApiResponse::paginated($orders, $rows);
    }

    public function invoiceDeliveryStats(Request $request): JsonResponse
    {
        $stats = $this->invoiceDeliveryListService->stats([
            'search' => $request->query('search'),
            'payment_status' => $request->query('payment_status'),
            'delivery_status' => $request->query('delivery_status'),
            'employee_id' => $request->query('employee_id'),
            'branch_id' => $request->query('branch_id'),
            'event_date_from' => $request->query('event_date_from'),
            'event_date_to' => $request->query('event_date_to'),
        ]);

        return ApiResponse::success($stats);
    }

    public function deliveries(Request $request): JsonResponse
    {
        return $this->invoiceDeliveries($request);
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
