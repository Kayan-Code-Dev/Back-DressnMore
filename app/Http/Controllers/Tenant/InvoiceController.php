<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Invoice\AddInvoicePaymentRequest;
use App\Http\Requests\Tenant\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Tenant\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\Tenant\InvoicePaymentResource;
use App\Http\Resources\Tenant\InvoiceResource;
use App\Services\Tenant\InvoicePaymentService;
use App\Services\Tenant\InvoiceService;
use App\Support\ApiResponse;
use App\Support\CsvExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoicePaymentService $invoicePaymentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $invoices = $this->invoiceService->paginate([
            'search' => $request->query('search'),
            'customer_id' => $request->query('customer_id'),
            'client_id' => $request->query('client_id'),
            'branch_id' => $request->query('branch_id'),
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ], $perPage);

        return ApiResponse::paginated($invoices, InvoiceResource::collection($invoices->items())->resolve());
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->create(
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return ApiResponse::success(new InvoiceResource($invoice), 'Invoice created', 201);
    }

    public function show(int $invoice): JsonResponse
    {
        $invoiceModel = $this->invoiceService->findOrFail($invoice);

        return ApiResponse::success(new InvoiceResource($invoiceModel));
    }

    public function update(UpdateInvoiceRequest $request, int $invoice): JsonResponse
    {
        $invoiceModel = $this->invoiceService->findOrFail($invoice);
        $invoiceModel = $this->invoiceService->update(
            invoice: $invoiceModel,
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return ApiResponse::success(new InvoiceResource($invoiceModel), 'Invoice updated');
    }

    public function destroy(int $invoice): JsonResponse
    {
        $invoiceModel = $this->invoiceService->findOrFail($invoice);
        $this->invoiceService->delete($invoiceModel);

        return ApiResponse::success(null, 'Invoice deleted');
    }

    public function payments(Request $request, int $invoice): JsonResponse
    {
        $invoiceModel = $this->invoiceService->findOrFail($invoice);
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $payments = $this->invoicePaymentService->paginateForInvoice($invoiceModel, $perPage);

        return ApiResponse::paginated($payments, InvoicePaymentResource::collection($payments->items())->resolve());
    }

    public function addPayment(AddInvoicePaymentRequest $request, int $invoice): JsonResponse
    {
        $invoiceModel = $this->invoiceService->findOrFail($invoice);
        $invoiceModel = $this->invoicePaymentService->addPayment(
            invoice: $invoiceModel,
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return ApiResponse::success(new InvoiceResource($invoiceModel), 'Payment added');
    }

    public function cancel(int $invoice): JsonResponse
    {
        $invoiceModel = $this->invoiceService->findOrFail($invoice);
        $invoiceModel = $this->invoiceService->cancel($invoiceModel);

        return ApiResponse::success(new InvoiceResource($invoiceModel), 'Invoice cancelled');
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->invoiceService->exportRows([
            'search' => $request->query('search'),
            'customer_id' => $request->query('customer_id'),
            'client_id' => $request->query('client_id'),
            'branch_id' => $request->query('branch_id'),
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ]);

        return CsvExporter::download(
            filename: 'invoices.csv',
            headers: ['ID', 'Invoice Number', 'Customer ID', 'Branch ID', 'Type', 'Status', 'Total', 'Paid', 'Remaining', 'Delivery Date', 'Created At'],
            rows: $rows
        );
    }
}
