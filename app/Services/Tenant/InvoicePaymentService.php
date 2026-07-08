<?php

namespace App\Services\Tenant;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Tenant\Cashbox;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoicePayment;
use App\Models\Tenant\JournalEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoicePaymentService
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly CashMovementService $cashMovementService,
        private readonly JournalEntryPostingService $journalEntryPostingService,
    ) {}

    public function store(array $data, ?int $actorId = null): InvoicePayment
    {
        $kind = (string) ($data['kind'] ?? 'invoice');
        $branchId = (int) $data['branch_id'];
        $cashboxId = (int) $data['cashbox_id'];
        $this->assertCashboxBelongsToBranch($cashboxId, $branchId);

        if ($kind === 'invoice') {
            $invoice = $this->invoiceService->findOrFail((int) $data['invoice_id']);

            return $this->recordPaidPayment($invoice, array_merge($data, [
                'branch_id' => $branchId,
                'cashbox_id' => $cashboxId,
            ]), $actorId);
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['مبلغ الدفعة يجب أن يكون أكبر من صفر'],
            ]);
        }

        /** @var InvoicePayment $payment */
        $payment = DB::connection('tenant')->transaction(function () use ($data, $amount, $branchId, $cashboxId, $actorId): InvoicePayment {
            $payment = InvoicePayment::query()->create([
                'invoice_id' => null,
                'branch_id' => $branchId,
                'cashbox_id' => $cashboxId,
                'amount' => $amount,
                'status' => InvoicePayment::STATUS_PAID,
                'payment_type' => PaymentType::MANUAL_ADJUSTMENT->value,
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'paid_at' => isset($data['paid_at']) ? Carbon::parse((string) $data['paid_at']) : Carbon::now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            $this->cashMovementService->recordInvoicePayment($payment, $actorId);

            return $payment;
        });

        return $payment->refresh()->load(['branch', 'cashbox']);
    }

    public function addPayment(Invoice $invoice, array $data, ?int $actorId = null): Invoice
    {
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'invoice' => ['Cannot add payment to a cancelled invoice'],
            ]);
        }

        $this->recordPaidPayment($invoice, $data, $actorId);

        return $invoice->refresh()->load(['items.dress.category', 'items.dress.subcategory', 'payments']);
    }

    public function recordPaidPayment(Invoice $invoice, array $data, ?int $actorId = null): InvoicePayment
    {
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'invoice' => ['لا يمكن إضافة دفعة لفاتورة ملغاة'],
            ]);
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['مبلغ الدفعة يجب أن يكون أكبر من صفر'],
            ]);
        }

        $invoice->refresh();
        $remaining = round((float) $invoice->remaining_amount, 2);
        if ($amount > $remaining + 0.009) {
            throw ValidationException::withMessages([
                'amount' => ['مبلغ الدفعة يتجاوز المبلغ المتبقي على الفاتورة'],
            ]);
        }

        /** @var InvoicePayment $payment */
        $payment = DB::connection('tenant')->transaction(function () use ($invoice, $data, $amount, $actorId): InvoicePayment {
            $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : (int) ($invoice->branch_id ?? 0);
            $cashboxId = isset($data['cashbox_id']) ? (int) $data['cashbox_id'] : null;
            if ($cashboxId !== null && $branchId > 0) {
                $this->assertCashboxBelongsToBranch($cashboxId, $branchId);
            }

            $payment = InvoicePayment::query()->create([
                'invoice_id' => $invoice->id,
                'branch_id' => $branchId > 0 ? $branchId : $invoice->branch_id,
                'cashbox_id' => $cashboxId,
                'amount' => $amount,
                'status' => InvoicePayment::STATUS_PAID,
                'payment_type' => InvoicePayment::TYPE_INVOICE_PAYMENT,
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'paid_at' => isset($data['paid_at']) ? Carbon::parse((string) $data['paid_at']) : Carbon::now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            $this->cashMovementService->recordInvoicePayment($payment, $actorId);
            $this->invoiceService->refreshFinancials($invoice->refresh());

            return $payment;
        });

        $this->journalEntryPostingService->postFromInvoicePayment($payment, $actorId);

        return $payment->refresh();
    }

    public function paginateForInvoice(Invoice $invoice, int $perPage = 15): LengthAwarePaginator
    {
        return $invoice->payments()
            ->with('invoice')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = InvoicePayment::query()
            ->with(['invoice.customer', 'invoice.branch', 'branch', 'cashbox'])
            ->latest('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $wildcard = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($wildcard): void {
                $builder->whereRaw('LOWER(reference) LIKE ?', [$wildcard])
                    ->orWhereRaw('LOWER(notes) LIKE ?', [$wildcard])
                    ->orWhereHas('invoice', fn (Builder $q) => $q->whereRaw('LOWER(invoice_number) LIKE ?', [$wildcard]));
            });
        }

        $this->applyExactFilter($query, 'status', $filters['status'] ?? null);
        $this->applyExactFilter($query, 'payment_type', $filters['payment_type'] ?? null);
        $this->applyExactFilter($query, 'method', $filters['method'] ?? null);
        $this->applyExactFilter($query, 'invoice_id', $filters['invoice_id'] ?? ($filters['order_id'] ?? null));

        $clientId = $filters['customer_id'] ?? ($filters['client_id'] ?? null);
        if ($clientId !== null && trim((string) $clientId) !== '') {
            $query->whereHas('invoice', fn (Builder $q) => $q->where('customer_id', $clientId));
        }

        $branchId = $filters['branch_id'] ?? null;
        if ($branchId !== null && trim((string) $branchId) !== '') {
            $query->where(function (Builder $builder) use ($branchId): void {
                $builder->where('branch_id', $branchId)
                    ->orWhereHas('invoice', fn (Builder $q) => $q->where('branch_id', $branchId));
            });
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->whereDate('paid_at', '>=', $dateFrom);
        }
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->whereDate('paid_at', '<=', $dateTo);
        }

        $amountMin = $filters['amount_min'] ?? null;
        if ($amountMin !== null && trim((string) $amountMin) !== '') {
            $query->where('amount', '>=', (float) $amountMin);
        }
        $amountMax = $filters['amount_max'] ?? null;
        if ($amountMax !== null && trim((string) $amountMax) !== '') {
            $query->where('amount', '<=', (float) $amountMax);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function findPaymentOrFail(int $paymentId): InvoicePayment
    {
        return InvoicePayment::query()->with('invoice')->findOrFail($paymentId);
    }

    public function markPaid(InvoicePayment $payment, ?int $actorId = null): InvoicePayment
    {
        if ($payment->status === InvoicePayment::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'payment' => ['Cancelled payment cannot be marked as paid'],
            ]);
        }

        /** @var InvoicePayment $updatedPayment */
        $updatedPayment = DB::connection('tenant')->transaction(function () use ($payment, $actorId): InvoicePayment {
            if ($payment->status !== InvoicePayment::STATUS_PAID) {
                $payment->status = InvoicePayment::STATUS_PAID;
                $payment->payment_type = $payment->payment_type ?: PaymentType::INVOICE_PAYMENT->value;
                $payment->paid_at = $payment->paid_at ?? Carbon::now();
                $payment->save();
            }

            $existingMovement = CashMovement::query()
                ->where('reference_type', CashMovement::REFERENCE_INVOICE_PAYMENT)
                ->where('reference_id', $payment->id)
                ->exists();

            if (! $existingMovement) {
                $this->cashMovementService->recordInvoicePayment($payment, $actorId);
            }

            if ($payment->invoice_id) {
                $this->invoiceService->refreshFinancials($payment->invoice()->firstOrFail()->refresh());
            }

            return $payment->refresh();
        });

        $this->journalEntryPostingService->postFromInvoicePayment($updatedPayment, $actorId);

        return $updatedPayment->load('invoice');
    }

    public function cancel(InvoicePayment $payment): InvoicePayment
    {
        if ($payment->status === InvoicePayment::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'payment' => ['Payment already cancelled'],
            ]);
        }

        /** @var InvoicePayment $updatedPayment */
        $updatedPayment = DB::connection('tenant')->transaction(function () use ($payment): InvoicePayment {
            $payment->status = PaymentStatus::CANCELLED->value;
            $payment->cancelled_at = Carbon::now();
            $payment->save();

            $this->cashMovementService->markReferenceReversed(
                referenceType: CashMovement::REFERENCE_INVOICE_PAYMENT,
                referenceId: (int) $payment->id,
            );

            $this->journalEntryPostingService->cancelBySource(JournalEntry::SOURCE_PAYMENT, (int) $payment->id, null);

            if ($payment->invoice_id) {
                $this->invoiceService->refreshFinancials($payment->invoice()->firstOrFail()->refresh());
            }

            return $payment->refresh();
        });

        return $updatedPayment->load('invoice');
    }

    /**
     * @return list<array<int|string,mixed>>
     */
    public function exportRows(array $filters): array
    {
        $rows = $this->paginate($filters, 1000)->items();

        return array_map(static function (InvoicePayment $payment): array {
            return [
                $payment->id,
                $payment->invoice_id,
                $payment->invoice?->invoice_number,
                $payment->invoice?->customer_id,
                $payment->invoice?->branch_id ?? $payment->branch_id,
                $payment->payment_type,
                $payment->status,
                $payment->amount,
                $payment->method,
                $payment->reference,
                $payment->paid_at?->toDateTimeString(),
            ];
        }, $rows);
    }

    private function assertCashboxBelongsToBranch(int $cashboxId, int $branchId): void
    {
        $cashbox = Cashbox::query()->find($cashboxId);
        if (! $cashbox instanceof Cashbox || (int) $cashbox->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'cashbox_id' => ['الخزنة المحددة لا تتبع الفرع المختار'],
            ]);
        }
    }

    private function applyExactFilter(Builder $query, string $column, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return;
        }

        $query->where($column, $normalized);
    }
}
