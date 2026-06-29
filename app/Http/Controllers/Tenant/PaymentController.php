<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Payment\StorePaymentRequest;
use App\Http\Resources\Tenant\InvoicePaymentResource;
use App\Services\Tenant\InvoicePaymentService;
use App\Support\ApiResponse;
use App\Support\Reports\TabularExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly InvoicePaymentService $invoicePaymentService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $payments = $this->invoicePaymentService->paginate([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'payment_type' => $request->query('payment_type'),
            'branch_id' => $request->query('branch_id'),
            'customer_id' => $request->query('customer_id'),
            'client_id' => $request->query('client_id'),
            'invoice_id' => $request->query('invoice_id'),
            'order_id' => $request->query('order_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'method' => $request->query('method'),
            'amount_min' => $request->query('amount_min'),
            'amount_max' => $request->query('amount_max'),
        ], $perPage);

        return ApiResponse::paginated($payments, InvoicePaymentResource::collection($payments->items())->resolve());
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->invoicePaymentService->store($request->validated(), $request->user()?->id);

        return ApiResponse::success(new InvoicePaymentResource($payment), 'Payment recorded', 201);
    }

    public function show(int $payment): JsonResponse
    {
        $paymentModel = $this->invoicePaymentService->findPaymentOrFail($payment);

        return ApiResponse::success(new InvoicePaymentResource($paymentModel));
    }

    public function pay(int $payment, Request $request): JsonResponse
    {
        $paymentModel = $this->invoicePaymentService->findPaymentOrFail($payment);
        $paymentModel = $this->invoicePaymentService->markPaid($paymentModel, $request->user()?->id);

        return ApiResponse::success(new InvoicePaymentResource($paymentModel), 'Payment marked as paid');
    }

    public function cancel(int $payment): JsonResponse
    {
        $paymentModel = $this->invoicePaymentService->findPaymentOrFail($payment);
        $paymentModel = $this->invoicePaymentService->cancel($paymentModel);

        return ApiResponse::success(new InvoicePaymentResource($paymentModel), 'Payment cancelled');
    }

    public function export(Request $request): StreamedResponse|Response
    {
        $rows = $this->invoicePaymentService->exportRows([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'payment_type' => $request->query('payment_type'),
            'branch_id' => $request->query('branch_id'),
            'customer_id' => $request->query('customer_id'),
            'client_id' => $request->query('client_id'),
            'invoice_id' => $request->query('invoice_id'),
            'order_id' => $request->query('order_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'amount_min' => $request->query('amount_min'),
            'amount_max' => $request->query('amount_max'),
        ]);

        $headers = ['ID', 'Invoice ID', 'Invoice Number', 'Customer ID', 'Branch ID', 'Payment Type', 'Status', 'Amount', 'Method', 'Reference', 'Paid At'];

        return TabularExport::download(
            $request->query('format'),
            'payments',
            'المدفوعات',
            $headers,
            $rows,
        );
    }
}
