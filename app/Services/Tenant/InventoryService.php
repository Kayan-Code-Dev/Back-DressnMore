<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Dress;
use App\Models\Tenant\InventoryMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InventoryService
{
    public function recordMovement(
        Dress $dress,
        string $type,
        int $quantity = 1,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $createdBy = null,
        ?int $fromBranchId = null,
        ?int $toBranchId = null,
    ): InventoryMovement {
        return InventoryMovement::query()->create([
            'dress_id' => $dress->id,
            'type' => $type,
            'quantity' => $quantity,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $createdBy,
            'from_branch_id' => $fromBranchId,
            'to_branch_id' => $toBranchId,
        ]);
    }

    public function paginateForDress(Dress $dress, int $perPage = 15): LengthAwarePaginator
    {
        return $dress->inventoryMovements()
            ->with(['fromBranch', 'toBranch'])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
