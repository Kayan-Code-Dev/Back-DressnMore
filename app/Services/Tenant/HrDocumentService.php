<?php

namespace App\Services\Tenant;

use App\Enums\HrDocumentStatus;
use App\Models\Tenant\HrDocument;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class HrDocumentService
{
    public function __construct(
        private readonly HrSettingService $hrSettingService,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = HrDocument::query()->with('employee')->latest('id');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage)->withQueryString();
    }

    public function create(array $data, ?int $uploadedBy = null): HrDocument
    {
        $data = $this->storeUploadedFile($data);

        if ($uploadedBy !== null) {
            $data['uploaded_by'] = $uploadedBy;
        }

        $data['status'] = $this->resolveStatus(
            $data['status'] ?? null,
            $data['expiry_date'] ?? null,
        );

        $document = HrDocument::query()->create($data);

        return $document->load('employee');
    }

    public function findOrFail(int $documentId): HrDocument
    {
        return HrDocument::query()->with('employee')->findOrFail($documentId);
    }

    public function update(HrDocument $document, array $data): HrDocument
    {
        $data = $this->storeUploadedFile($data, $document);

        $document->fill($data);
        $document->status = $this->resolveStatus(
            $document->status,
            $document->expiry_date?->toDateString(),
        );
        $document->save();

        return $document->refresh()->load('employee');
    }

    public function delete(HrDocument $document): void
    {
        if (is_string($document->file_path) && $document->file_path !== '') {
            Storage::disk('local')->delete($document->file_path);
        }

        $document->delete();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function expiryAlerts(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $alertDays = $this->hrSettingService->documentExpiryAlertDays();

        $query = HrDocument::query()
            ->with('employee')
            ->whereNotNull('expiry_date')
            ->where(function ($builder) use ($alertDays): void {
                $builder
                    ->where('status', HrDocumentStatus::EXPIRED->value)
                    ->orWhere('status', HrDocumentStatus::EXPIRING_SOON->value)
                    ->orWhereDate('expiry_date', '<=', CarbonImmutable::today()->addDays($alertDays));
            })
            ->orderBy('expiry_date');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage)->withQueryString();
    }

    public function countExpiring(): int
    {
        $alertDays = $this->hrSettingService->documentExpiryAlertDays();

        return HrDocument::query()
            ->whereNotNull('expiry_date')
            ->where(function ($builder) use ($alertDays): void {
                $builder
                    ->where('status', HrDocumentStatus::EXPIRING_SOON->value)
                    ->orWhereDate('expiry_date', '<=', CarbonImmutable::today()->addDays($alertDays))
                    ->whereDate('expiry_date', '>=', CarbonImmutable::today());
            })
            ->count();
    }

    /**
     * @param  Builder<HrDocument>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['expires_before'])) {
            $query->whereDate('expiry_date', '<=', $filters['expires_before']);
        }
    }

    private function resolveStatus(?string $requestedStatus, mixed $expiryDate): string
    {
        if ($requestedStatus === HrDocumentStatus::MISSING->value) {
            return HrDocumentStatus::MISSING->value;
        }

        if ($expiryDate === null || $expiryDate === '') {
            return HrDocumentStatus::VALID->value;
        }

        $expiry = CarbonImmutable::parse($expiryDate)->startOfDay();
        $today = CarbonImmutable::today();

        if ($expiry->lt($today)) {
            return HrDocumentStatus::EXPIRED->value;
        }

        $alertDays = $this->hrSettingService->documentExpiryAlertDays();
        if ($expiry->lte($today->addDays($alertDays))) {
            return HrDocumentStatus::EXPIRING_SOON->value;
        }

        return HrDocumentStatus::VALID->value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function storeUploadedFile(array $data, ?HrDocument $existingDocument = null): array
    {
        $file = $data['file'] ?? null;
        unset($data['file']);

        if (! $file instanceof UploadedFile) {
            return $data;
        }

        $tenant = $this->tenantContext->requireTenant();
        $employeeId = (int) ($data['employee_id'] ?? $existingDocument?->employee_id);
        $directory = 'tenants/'.$tenant->id.'/hr/documents/'.$employeeId;
        $path = $file->store($directory, 'local');

        if ($existingDocument instanceof HrDocument
            && is_string($existingDocument->file_path)
            && $existingDocument->file_path !== '') {
            Storage::disk('local')->delete($existingDocument->file_path);
        }

        $data['file_path'] = $path;
        $data['file_name'] = trim((string) ($data['file_name'] ?? '')) !== ''
            ? $data['file_name']
            : $file->getClientOriginalName();

        return $data;
    }
}
