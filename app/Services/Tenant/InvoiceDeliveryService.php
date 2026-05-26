<?php

namespace App\Services\Tenant;

use App\Models\Tenant\DeliveryRecord;
use App\Models\Tenant\Dress;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceDeliveryService
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function deliver(Invoice $invoice, array $data, ?int $actorId = null): Invoice
    {
        $this->ensureDeliverable($invoice);

        /** @var Invoice $updated */
        $updated = DB::connection('tenant')->transaction(function () use ($invoice, $data, $actorId): Invoice {
            $deliveredAt = isset($data['delivered_at'])
                ? Carbon::parse((string) $data['delivered_at'])
                : Carbon::now();

            $invoice->fill([
                'status' => Invoice::STATUS_DELIVERED,
                'delivery_date' => $deliveredAt->toDateString(),
            ]);
            $invoice->save();

            if (in_array($invoice->type, [Invoice::TYPE_RENT, Invoice::TYPE_SELL], true)) {
                $targetStatus = $invoice->type === Invoice::TYPE_RENT
                    ? Dress::STATUS_RENTED
                    : Dress::STATUS_SOLD;

                $movementType = $invoice->type === Invoice::TYPE_RENT
                    ? \App\Models\Tenant\InventoryMovement::TYPE_RENTED
                    : \App\Models\Tenant\InventoryMovement::TYPE_SOLD;

                /** @var InvoiceItem $item */
                foreach ($invoice->items()->whereNotNull('dress_id')->with('dress')->get() as $item) {
                    $dress = $item->dress;
                    if (! $dress instanceof Dress) {
                        continue;
                    }

                    $dress->status = $targetStatus;
                    $dress->save();

                    $this->inventoryService->recordMovement(
                        dress: $dress,
                        type: $movementType,
                        quantity: max(1, (int) $item->quantity),
                        reason: 'Invoice delivered',
                        referenceType: Invoice::class,
                        referenceId: $invoice->id,
                        notes: $data['notes'] ?? null,
                        createdBy: $actorId,
                    );
                }
            }

            DeliveryRecord::query()->create([
                'invoice_id' => $invoice->id,
                'type' => DeliveryRecord::TYPE_DELIVERED,
                'delivered_at' => $deliveredAt,
                'receiver_name' => $data['receiver_name'] ?? null,
                'receiver_phone' => $data['receiver_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            return $invoice->refresh();
        });

        return $updated->load(['items.dress.category', 'items.dress.subcategory', 'payments']);
    }

    public function returnRentInvoice(Invoice $invoice, array $data, ?int $actorId = null): Invoice
    {
        $this->ensureReturnable($invoice);

        /** @var Invoice $updated */
        $updated = DB::connection('tenant')->transaction(function () use ($invoice, $data, $actorId): Invoice {
            $returnedAt = isset($data['returned_at'])
                ? Carbon::parse((string) $data['returned_at'])
                : Carbon::now();

            $statusAfterReturn = (string) ($data['dress_status_after_return'] ?? Dress::STATUS_AVAILABLE);

            $invoice->fill([
                'status' => Invoice::STATUS_RETURNED,
                'return_date' => $returnedAt->toDateString(),
            ]);
            $invoice->save();

            /** @var InvoiceItem $item */
            foreach ($invoice->items()->whereNotNull('dress_id')->with('dress')->get() as $item) {
                $dress = $item->dress;
                if (! $dress instanceof Dress) {
                    continue;
                }

                $dress->status = $statusAfterReturn;
                $dress->save();

                $this->inventoryService->recordMovement(
                    dress: $dress,
                    type: \App\Models\Tenant\InventoryMovement::TYPE_RETURNED,
                    quantity: max(1, (int) $item->quantity),
                    reason: 'Invoice returned',
                    referenceType: Invoice::class,
                    referenceId: $invoice->id,
                    notes: $data['notes'] ?? null,
                    createdBy: $actorId,
                );

                if ($statusAfterReturn === Dress::STATUS_MAINTENANCE) {
                    $this->inventoryService->recordMovement(
                        dress: $dress,
                        type: \App\Models\Tenant\InventoryMovement::TYPE_MAINTENANCE,
                        quantity: max(1, (int) $item->quantity),
                        reason: 'Moved to maintenance after return',
                        referenceType: Invoice::class,
                        referenceId: $invoice->id,
                        notes: $data['notes'] ?? null,
                        createdBy: $actorId,
                    );
                }
            }

            DeliveryRecord::query()->create([
                'invoice_id' => $invoice->id,
                'type' => DeliveryRecord::TYPE_RETURNED,
                'returned_at' => $returnedAt,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            return $invoice->refresh();
        });

        return $updated->load(['items.dress.category', 'items.dress.subcategory', 'payments']);
    }

    public function paginateDeliveryRecords(Invoice $invoice, int $perPage = 15): LengthAwarePaginator
    {
        return $invoice->deliveryRecords()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function ensureDeliverable(Invoice $invoice): void
    {
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'invoice' => ['Cancelled invoice cannot be delivered'],
            ]);
        }

        if ($invoice->deliveryRecords()->where('type', DeliveryRecord::TYPE_DELIVERED)->exists()
            || $invoice->status === Invoice::STATUS_DELIVERED
            || $invoice->status === Invoice::STATUS_RETURNED) {
            throw ValidationException::withMessages([
                'invoice' => ['Invoice is already delivered'],
            ]);
        }

        if (! in_array($invoice->status, [
            Invoice::STATUS_CONFIRMED,
            Invoice::STATUS_PARTIALLY_PAID,
            Invoice::STATUS_PAID,
        ], true)) {
            throw ValidationException::withMessages([
                'invoice' => ['Invoice must be confirmed or paid before delivery'],
            ]);
        }
    }

    private function ensureReturnable(Invoice $invoice): void
    {
        if ($invoice->type !== Invoice::TYPE_RENT) {
            throw ValidationException::withMessages([
                'invoice' => ['Only rent invoices can be returned'],
            ]);
        }

        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'invoice' => ['Cancelled invoice cannot be returned'],
            ]);
        }

        if ($invoice->deliveryRecords()->where('type', DeliveryRecord::TYPE_RETURNED)->exists()
            || $invoice->status === Invoice::STATUS_RETURNED) {
            throw ValidationException::withMessages([
                'invoice' => ['Invoice is already returned'],
            ]);
        }

        $wasDelivered = $invoice->deliveryRecords()
            ->where('type', DeliveryRecord::TYPE_DELIVERED)
            ->exists();

        if (! $wasDelivered && $invoice->status !== Invoice::STATUS_DELIVERED) {
            throw ValidationException::withMessages([
                'invoice' => ['Invoice must be delivered before return'],
            ]);
        }
    }
}
