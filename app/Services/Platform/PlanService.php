<?php

namespace App\Services\Platform;

use App\Models\Central\Plan;
use App\Models\Central\PlanFeature;
use App\Support\PlanFeatureCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use RuntimeException;

class PlanService
{
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Plan::query()->with('features')->orderBy('sort_order')->orderBy('id');

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function findOrFail(int $planId): Plan
    {
        return Plan::query()->with('features')->findOrFail($planId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Plan
    {
        $payload = $this->normalizePlanPayload($data);
        $plan = Plan::query()->create($payload);
        $this->syncFeatures($plan, $data['features'] ?? []);

        return $plan->refresh()->load('features');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Plan $plan, array $data): Plan
    {
        $payload = $this->normalizePlanPayload($data, $plan);
        $plan->fill($payload);
        $plan->save();

        if (array_key_exists('features', $data)) {
            $this->syncFeatures($plan, is_array($data['features']) ? $data['features'] : []);
        }

        return $plan->refresh()->load('features');
    }

    public function destroy(Plan $plan): void
    {
        if ($plan->tenants()->exists()) {
            throw new RuntimeException('Cannot delete a plan that is assigned to tenants.');
        }

        $plan->delete();
    }

    /**
     * @param  array<string, mixed>  $features
     */
    public function syncFeatures(Plan $plan, array $features): void
    {
        $allowedKeys = PlanFeatureCatalog::keys();
        $rows = [];

        foreach ($features as $key => $value) {
            $featureKey = is_string($key) ? $key : null;
            if ($featureKey === null || ! in_array($featureKey, $allowedKeys, true)) {
                continue;
            }

            $rows[$featureKey] = [
                'feature_key' => $featureKey,
                'feature_value' => PlanFeatureCatalog::normalizeValue($featureKey, $value),
                'value_type' => PlanFeatureCatalog::valueType($featureKey),
            ];
        }

        PlanFeature::query()->where('plan_id', $plan->id)->delete();

        foreach ($rows as $row) {
            PlanFeature::query()->create([
                'plan_id' => $plan->id,
                ...$row,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePlanPayload(array $data, ?Plan $existing = null): array
    {
        $name = trim((string) ($data['name'] ?? $data['title'] ?? $existing?->name ?? ''));
        $slug = trim((string) ($data['slug'] ?? $existing?->slug ?? ''));
        if ($slug === '') {
            $slug = Str::slug($name);
        }

        $status = $data['status'] ?? null;
        if ($status === null && array_key_exists('is_active', $data)) {
            $status = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) ? 'active' : 'inactive';
        }
        if ($status === null) {
            $status = $existing?->status ?? 'active';
        }

        $durationDays = $data['duration_days'] ?? $data['days'] ?? $existing?->duration_days ?? 365;

        return [
            'name' => $name,
            'slug' => $slug,
            'description' => trim((string) ($data['description'] ?? $existing?->description ?? '')),
            'price' => (float) ($data['price'] ?? $existing?->price ?? 0),
            'billing_cycle' => (string) ($data['billing_cycle'] ?? $existing?->billing_cycle ?? 'monthly'),
            'duration_days' => max(1, (int) $durationDays),
            'sort_order' => (int) ($data['sort_order'] ?? $existing?->sort_order ?? 0),
            'status' => (string) $status,
        ];
    }
}
