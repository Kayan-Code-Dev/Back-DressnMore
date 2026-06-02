<?php

namespace App\Services\Tenant;

use App\Models\Tenant\HrShift;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HrShiftService
{
    public function paginate(?int $branchId = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = HrShift::query()
            ->with('branch')
            ->latest('id');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $statusValue = trim((string) $status);
        if ($statusValue !== '') {
            $query->where('status', $statusValue);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data): HrShift
    {
        return HrShift::query()->create($data)->load('branch');
    }

    public function findOrFail(int $id): HrShift
    {
        return HrShift::query()->with('branch')->findOrFail($id);
    }

    public function update(HrShift $shift, array $data): HrShift
    {
        $shift->fill($data);
        $shift->save();

        return $shift->refresh()->load('branch');
    }

    public function delete(HrShift $shift): void
    {
        $shift->delete();
    }
}
