<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Sales\StoreSaleInvoiceRequest;
use App\Services\Tenant\SalesService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function __construct(private readonly SalesService $salesService) {}

    public function indexInvoices(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $invoices = $this->salesService->paginateInvoices([
            'search' => $request->query('search'),
            'payment_status' => $request->query('payment_status'),
            'invoice_status' => $request->query('invoice_status'),
            'branch_id' => $request->query('branch_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ], $perPage);

        return ApiResponse::paginated($invoices, $invoices->items());
    }

    public function invoiceStats(Request $request): JsonResponse
    {
        $stats = $this->salesService->invoiceStats([
            'search' => $request->query('search'),
            'payment_status' => $request->query('payment_status'),
            'invoice_status' => $request->query('invoice_status'),
            'branch_id' => $request->query('branch_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ]);

        return ApiResponse::success($stats);
    }

    public function storeInvoice(StoreSaleInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->salesService->createSale($request->validated(), $request->user()?->id);

        return ApiResponse::success(['id' => $invoice->id], 'Sale invoice created', 201);
    }

    public function reportSummary(Request $request): JsonResponse
    {
        return ApiResponse::success($this->salesService->reportSummary([
            'period' => $request->query('period'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'branch_id' => $request->query('branch_id'),
        ]));
    }

    public function reportDaily(Request $request): JsonResponse
    {
        return ApiResponse::success($this->salesService->dailySales([
            'period' => $request->query('period'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'branch_id' => $request->query('branch_id'),
        ]));
    }

    public function reportProducts(Request $request): JsonResponse
    {
        return ApiResponse::success($this->salesService->productSales([
            'period' => $request->query('period'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'branch_id' => $request->query('branch_id'),
        ]));
    }

    public function reportByEmployee(Request $request): JsonResponse
    {
        return ApiResponse::success($this->salesService->employeeSales([
            'period' => $request->query('period'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'branch_id' => $request->query('branch_id'),
        ]));
    }
}
