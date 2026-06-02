<?php

namespace App\Services\Tenant;

use App\Models\Tenant\HrJobTitle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HrJobTitleService
{
    public function paginate(
        ?string $search = null,
        ?int $departmentId = null,
        ?string $status = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = HrJobTitle::query()->with('department')->latest('id');

        $searchTerm = trim((string) $search);
        if ($searchTerm !== '') {
            $query->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($searchTerm).'%']);
        }

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        }

        $statusValue = trim((string) $status);
        if ($statusValue !== '') {
            $query->where('status', $statusValue);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data): HrJobTitle
    {
        $jobTitle = HrJobTitle::query()->create($data);

        return $jobTitle->load('department');
    }

    public function findOrFail(int $jobTitleId): HrJobTitle
    {
        return HrJobTitle::query()->with('department')->findOrFail($jobTitleId);
    }

    public function update(HrJobTitle $jobTitle, array $data): HrJobTitle
    {
        $jobTitle->fill($data);
        $jobTitle->save();

        return $jobTitle->refresh()->load('department');
    }

    public function delete(HrJobTitle $jobTitle): void
    {
        $jobTitle->delete();
    }
}
