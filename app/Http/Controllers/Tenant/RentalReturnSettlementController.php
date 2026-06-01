<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Returns\SettleRentalReturnRequest;
use App\Http\Requests\Tenant\Returns\SettlementPreviewRequest;
use App\Http\Resources\Tenant\InvoiceResource;
use App\Http\Resources\Tenant\RentalReturnSettlementResource;
use App\Services\Tenant\InvoiceService;
use App\Services\Tenant\RentalReturnSettlementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class RentalReturnSettlementController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly RentalReturnSettlementService $settlementService,
    ) {}

    public function preview(SettlementPreviewRequest $request, int $invoice): JsonResponse
    {
        $invoiceModel = $this->invoiceService->findOrFail($invoice);
        $payload = $this->settlementService->preview($invoiceModel, $request->validated());

        return ApiResponse::success($payload, 'Settlement preview');
    }

    public function settle(SettleRentalReturnRequest $request, int $invoice): JsonResponse
    {
        $invoiceModel = $this->invoiceService->findOrFail($invoice);
        $settlement = $this->settlementService->settle(
            $invoiceModel,
            $request->validated(),
            $request->user()?->id,
        );

        return ApiResponse::success([
            'settlement' => new RentalReturnSettlementResource($settlement),
            'invoice' => new InvoiceResource($settlement->invoice),
        ], 'Rental return settled', 201);
    }
}
