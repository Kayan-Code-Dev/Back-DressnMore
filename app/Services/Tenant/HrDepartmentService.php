<?php

namespace App\Services\Tenant;

use App\Models\Tenant\HrDepartment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HrDepartmentService
{
    public function paginate(?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = HrDepartment::query()->latest('id');

        $searchTerm = trim((string) $search);
        if ($searchTerm !== '') {
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($searchTerm).'%']);
        }

        $statusValue = trim((string) $status);
        if ($statusValue !== '') {
            $query->where('status', $statusValue);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data): HrDepartment
    {
        return HrDepartment::query()->create($data);
    }

    public function findOrFail(int $departmentId): HrDepartment
    {
        return HrDepartment::query()->findOrFail($departmentId);
    }

    public function update(HrDepartment $department, array $data): HrDepartment
    {
        $department->fill($data);
        $department->save();

        return $department->refresh();
    }

    public function delete(HrDepartment $department): void
    {
        $department->delete();
    }
}
