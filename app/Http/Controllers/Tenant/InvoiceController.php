<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Invoice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Invoice::query()->with('items')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:tenant.customers,id'],
            'branch_id' => ['nullable', 'integer', 'exists:tenant.branches,id'],
            'invoice_number' => ['required', 'string', 'max:120', 'unique:tenant.invoices,invoice_number'],
            'status' => ['required', 'string', 'max:50'],
            'total' => ['required', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'remaining' => ['nullable', 'numeric', 'min:0'],
            'issued_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
        ]);

        $invoice = Invoice::query()->create($data);

        return ApiResponse::success($invoice, 'Created', 201);
    }

    public function show(int $invoice): JsonResponse
    {
        return ApiResponse::success(Invoice::query()->with('items')->findOrFail($invoice));
    }

    public function update(Request $request, int $invoice): JsonResponse
    {
        $invoiceModel = Invoice::query()->findOrFail($invoice);

        $data = $request->validate([
            'customer_id' => ['sometimes', 'integer', 'exists:tenant.customers,id'],
            'branch_id' => ['nullable', 'integer', 'exists:tenant.branches,id'],
            'invoice_number' => ['sometimes', 'string', 'max:120', 'unique:tenant.invoices,invoice_number,'.$invoiceModel->id],
            'status' => ['sometimes', 'string', 'max:50'],
            'total' => ['sometimes', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'remaining' => ['nullable', 'numeric', 'min:0'],
            'issued_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
        ]);

        $invoiceModel->update($data);

        return ApiResponse::success($invoiceModel->fresh()->load('items'), 'Updated');
    }

    public function destroy(int $invoice): JsonResponse
    {
        Invoice::query()->findOrFail($invoice)->delete();

        return ApiResponse::success(null, 'Deleted');
    }
}
