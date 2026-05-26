<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Delivery\AddSecurityDepositDeductionRequest;
use App\Http\Requests\Tenant\Delivery\DeliverInvoiceRequest;
use App\Http\Requests\Tenant\Delivery\ReturnInvoiceRequest;
use App\Http\Resources\Tenant\DeliveryRecordResource;
use App\Http\Resources\Tenant\InvoiceResource;
use App\Http\Resources\Tenant\SecurityDepositTransactionResource;
use App\Services\Tenant\InvoiceDeliveryService;
use App\Services\Tenant\InvoiceService;
use App\Services\Tenant\SecurityDepositService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceDeliveryController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceDeliveryService $invoiceDeliveryService,
        private readonly SecurityDepositService $securityDepositService
    ) {
    }

    public function deliver(DeliverInvoiceRequest $request, int $invoice): JsonResponse
    {
        $invoiceModel = $this->invoiceService->findOrFail($invoice);
        $invoiceModel = $this->invoiceDeliveryService->deliver(
            invoice: $invoiceModel,
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return ApiResponse::success(new InvoiceResource($invoiceModel), 'Invoice delivered');
    }

    public function returnInvoice(ReturnInvoiceRequest $request, int $invoice): JsonResponse
    {
        $invoiceModel = $this->invoiceService->findOrFail($invoice);
        $invoiceModel = $this->invoiceDeliveryService->returnRentInvoice(
            invoice: $invoiceModel,
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return ApiResponse::success(new InvoiceResource($invoiceModel), 'Invoice returned');
    }

    public function deliveryRecords(Request $request, int $invoice): JsonResponse
    {
        $invoiceModel = $this->invoiceService->findOrFail($invoice);
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $records = $this->invoiceDeliveryService->paginateDeliveryRecords($invoiceModel, $perPage);

        return ApiResponse::paginated(
            $records,
            DeliveryRecordResource::collection($records->items())->resolve()
        );
    }

    public function addSecurityDepositDeduction(
        AddSecurityDepositDeductionRequest $request,
        int $invoice
    ): JsonResponse {
        $invoiceModel = $this->invoiceService->findOrFail($invoice);
        $invoiceModel = $this->securityDepositService->addDeduction(
            invoice: $invoiceModel,
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return ApiResponse::success(new InvoiceResource($invoiceModel), 'Security deposit deduction added');
    }

    public function securityDepositTransactions(Request $request, int $invoice): JsonResponse
    {
        $invoiceModel = $this->invoiceService->findOrFail($invoice);
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $transactions = $this->securityDepositService->paginateTransactions($invoiceModel, $perPage);

        return ApiResponse::paginated(
            $transactions,
            SecurityDepositTransactionResource::collection($transactions->items())->resolve()
        );
    }
}
