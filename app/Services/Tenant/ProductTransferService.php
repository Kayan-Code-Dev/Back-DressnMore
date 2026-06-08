<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductTransfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductTransferService
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductTransfer::query()
            ->with(['product', 'fromBranch', 'toBranch', 'requestedBy'])
            ->latest('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder
                    ->whereRaw('LOWER(transfer_number) LIKE ?', [$needle])
                    ->orWhereHas('product', fn (Builder $productQuery) => $productQuery->whereRaw('LOWER(name) LIKE ?', [$needle]))
                    ->orWhereHas('requestedBy', fn (Builder $userQuery) => $userQuery->whereRaw('LOWER(name) LIKE ?', [$needle]));
            });
        }

        if (($filters['status'] ?? null) !== null && trim((string) $filters['status']) !== '') {
            $query->where('status', trim((string) $filters['status']));
        }

        if (($filters['from_branch_id'] ?? null) !== null && trim((string) $filters['from_branch_id']) !== '') {
            $query->where('from_branch_id', (int) $filters['from_branch_id']);
        }

        if (($filters['to_branch_id'] ?? null) !== null && trim((string) $filters['to_branch_id']) !== '') {
            $query->where('to_branch_id', (int) $filters['to_branch_id']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data, ?int $actorId = null): ProductTransfer
    {
        $product = Product::query()->findOrFail((int) $data['product_id']);

        $fromBranchId = isset($data['from_branch_id'])
            ? (int) $data['from_branch_id']
            : (int) $product->branch_id;
        $toBranchId = (int) $data['to_branch_id'];
        $quantity = (int) $data['quantity'];

        if ((int) $product->branch_id !== $fromBranchId) {
            throw ValidationException::withMessages([
                'from_branch_id' => ['Selected source branch does not match product branch'],
            ]);
        }

        if ($fromBranchId === $toBranchId) {
            throw ValidationException::withMessages([
                'to_branch_id' => ['Destination branch must be different from source branch'],
            ]);
        }

        if ((int) $product->quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['Transfer quantity exceeds available stock in source branch'],
            ]);
        }

        $transfer = ProductTransfer::query()->create([
            'transfer_number' => $this->generateTransferNumber(),
            'product_id' => $product->id,
            'from_branch_id' => $fromBranchId,
            'to_branch_id' => $toBranchId,
            'quantity' => $quantity,
            'scheduled_delivery_at' => $data['scheduled_delivery_at'] ?? null,
            'status' => ProductTransfer::STATUS_PENDING,
            'notes' => $data['notes'] ?? null,
            'requested_by' => $actorId,
        ]);

        return $this->findOrFail((int) $transfer->id);
    }

    public function findOrFail(int $transferId): ProductTransfer
    {
        return ProductTransfer::query()
            ->with(['product', 'fromBranch', 'toBranch', 'requestedBy'])
            ->findOrFail($transferId);
    }

    public function confirm(ProductTransfer $transfer, ?int $actorId = null): ProductTransfer
    {
        if ($transfer->status !== ProductTransfer::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'transfer' => ['Only pending transfers can be confirmed'],
            ]);
        }

        DB::connection('tenant')->transaction(function () use ($transfer, $actorId): void {
            $sourceProduct = Product::query()->lockForUpdate()->findOrFail((int) $transfer->product_id);

            if ((int) $sourceProduct->branch_id !== (int) $transfer->from_branch_id) {
                throw ValidationException::withMessages([
                    'transfer' => ['Product source branch changed and transfer cannot be confirmed'],
                ]);
            }

            if ((int) $sourceProduct->quantity < (int) $transfer->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['Not enough product stock to confirm this transfer'],
                ]);
            }

            $sourceProduct->quantity = (int) $sourceProduct->quantity - (int) $transfer->quantity;
            $sourceProduct->save();

            $targetProduct = Product::query()
                ->where('branch_id', $transfer->to_branch_id)
                ->where('sku', $sourceProduct->sku)
                ->lockForUpdate()
                ->first();

            if (! $targetProduct instanceof Product) {
                $targetProduct = Product::query()->create([
                    'branch_id' => $transfer->to_branch_id,
                    'sku' => $sourceProduct->sku,
                    'name' => $sourceProduct->name,
                    'description' => $sourceProduct->description,
                    'quantity' => 0,
                    'cost_price' => $sourceProduct->cost_price,
                    'sale_price' => $sourceProduct->sale_price,
                    'is_active' => $sourceProduct->is_active,
                    'created_by' => $actorId,
                ]);
            }

            $targetProduct->quantity = (int) $targetProduct->quantity + (int) $transfer->quantity;
            $targetProduct->save();

            $transfer->status = ProductTransfer::STATUS_CONFIRMED;
            $transfer->confirmed_by = $actorId;
            $transfer->confirmed_at = now();
            $transfer->rejected_by = null;
            $transfer->rejected_at = null;
            $transfer->rejection_reason = null;
            $transfer->save();
        });

        return $this->findOrFail((int) $transfer->id);
    }

    public function reject(ProductTransfer $transfer, ?string $reason = null, ?int $actorId = null): ProductTransfer
    {
        if ($transfer->status !== ProductTransfer::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'transfer' => ['Only pending transfers can be rejected'],
            ]);
        }

        $transfer->status = ProductTransfer::STATUS_REJECTED;
        $transfer->rejected_by = $actorId;
        $transfer->rejected_at = now();
        $transfer->rejection_reason = $reason;
        $transfer->save();

        return $this->findOrFail((int) $transfer->id);
    }

    public function delete(ProductTransfer $transfer): void
    {
        if ($transfer->status === ProductTransfer::STATUS_CONFIRMED) {
            throw ValidationException::withMessages([
                'transfer' => ['Confirmed transfer cannot be deleted'],
            ]);
        }

        $transfer->delete();
    }

    private function generateTransferNumber(): string
    {
        $prefix = 'TR-'.now()->format('Ymd');
        $sequence = ProductTransfer::withTrashed()
            ->where('transfer_number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $sequence);
    }
}
