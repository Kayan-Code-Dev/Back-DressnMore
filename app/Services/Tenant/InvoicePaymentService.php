<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoicePayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoicePaymentService
{
    public function __construct(private readonly InvoiceService $invoiceService)
    {
    }

    public function addPayment(Invoice $invoice, array $data, ?int $actorId = null): Invoice
    {
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'invoice' => ['Cannot add payment to a cancelled invoice'],
            ]);
        }

        /** @var Invoice $updatedInvoice */
        $updatedInvoice = DB::connection('tenant')->transaction(function () use ($invoice, $data, $actorId): Invoice {
            InvoicePayment::query()->create([
                'invoice_id' => $invoice->id,
                'amount' => round((float) $data['amount'], 2),
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'paid_at' => $data['paid_at'] ?? Carbon::now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            return $this->invoiceService->refreshFinancials($invoice->refresh());
        });

        return $updatedInvoice->refresh()->load(['items.dress.category', 'items.dress.subcategory', 'payments']);
    }

    public function paginateForInvoice(Invoice $invoice, int $perPage = 15): LengthAwarePaginator
    {
        return $invoice->payments()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
