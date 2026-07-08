<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SupplierPayment\StoreSupplierPaymentRequest;
use App\Http\Resources\Tenant\SupplierPaymentResource;
use App\Services\Tenant\PurchaseOrderService;
use App\Services\Tenant\SupplierPaymentService;
use App\Services\Tenant\SupplierService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierPaymentController extends Controller
{
    public function __construct(
        private readonly SupplierService $supplierService,
        private readonly SupplierPaymentService $supplierPaymentService,
        private readonly PurchaseOrderService $purchaseOrderService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $payments = $this->supplierPaymentService->paginateAll([
            'search' => $request->query('search'),
        ], $perPage);

        return ApiResponse::paginated($payments, SupplierPaymentResource::collection($payments->items())->resolve());
    }

    public function indexForSupplier(Request $request, int $supplier): JsonResponse
    {
        $supplierModel = $this->supplierService->findOrFail($supplier);
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $payments = $this->supplierPaymentService->paginateForSupplier($supplierModel, $perPage);

        return ApiResponse::paginated($payments, SupplierPaymentResource::collection($payments->items())->resolve());
    }

    public function storeForSupplier(StoreSupplierPaymentRequest $request, int $supplier): JsonResponse
    {
        $supplierModel = $this->supplierService->findOrFail($supplier);
        $payment = $this->supplierPaymentService->addPayment(
            supplier: $supplierModel,
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return ApiResponse::success(new SupplierPaymentResource($payment), 'Supplier payment added', 201);
    }

    public function indexForPurchaseOrder(Request $request, int $purchaseOrder): JsonResponse
    {
        $purchaseOrderModel = $this->purchaseOrderService->findOrFail($purchaseOrder);
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $payments = $this->supplierPaymentService->paginateForPurchaseOrder($purchaseOrderModel, $perPage);

        return ApiResponse::paginated($payments, SupplierPaymentResource::collection($payments->items())->resolve());
    }

    public function stats(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'total_payments' => \App\Models\Tenant\SupplierPayment::count(),
            'total_amount' => \App\Models\Tenant\SupplierPayment::sum('amount'),
            'this_month' => \App\Models\Tenant\SupplierPayment::whereMonth('created_at', now()->month)->sum('amount'),
            'last_month' => \App\Models\Tenant\SupplierPayment::whereMonth('created_at', now()->subMonth()->month)->sum('amount'),
        ]);
    }
}
