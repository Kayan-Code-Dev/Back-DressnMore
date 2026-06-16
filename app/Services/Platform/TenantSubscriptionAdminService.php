<?php

namespace App\Services\Platform;

use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TenantSubscriptionAdminService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Tenant::query()
            ->with(['plan'])
            ->whereNotNull('plan_id')
            ->orderByDesc('updated_at');

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where(function (Builder $builder) use ($status): void {
                if ($status === 'cancelled') {
                    $builder->whereNotNull('cancelled_at');
                } elseif ($status === 'active') {
                    $builder->whereNull('cancelled_at')
                        ->where(function (Builder $inner): void {
                            $inner->whereNull('subscription_ends_at')
                                ->orWhereDate('subscription_ends_at', '>=', CarbonImmutable::today());
                        });
                } elseif ($status === 'expired') {
                    $builder->whereNull('cancelled_at')
                        ->whereNotNull('subscription_ends_at')
                        ->whereDate('subscription_ends_at', '<', CarbonImmutable::today());
                } else {
                    $builder->where('status', $status);
                }
            });
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$needle])
                    ->orWhereHas('plan', fn (Builder $p) => $p->whereRaw('LOWER(name) LIKE ?', [$needle]));
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Tenant $tenant): array
    {
        $tenant->loadMissing(['plan']);
        $plan = $tenant->plan;
        $latest = Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->first();

        $endsAt = $tenant->subscription_ends_at !== null
            ? CarbonImmutable::parse((string) $tenant->subscription_ends_at)
            : null;
        $startsAt = $tenant->subscription_starts_at !== null
            ? CarbonImmutable::parse((string) $tenant->subscription_starts_at)
            : null;

        $status = 'active';
        if ($tenant->cancelled_at !== null) {
            $status = 'cancelled';
        } elseif ($endsAt !== null && $endsAt->lt(CarbonImmutable::today())) {
            $status = 'expired';
        } elseif ((string) $tenant->status !== 'active') {
            $status = (string) $tenant->status;
        }

        $daysRemaining = null;
        if ($endsAt !== null) {
            $daysRemaining = max(0, CarbonImmutable::today()->diffInDays($endsAt->startOfDay(), false));
        }

        return [
            'id' => $latest?->id ?? (int) $tenant->id,
            'tenant_id' => (string) $tenant->id,
            'plan_id' => (int) ($tenant->plan_id ?? 0),
            'status' => $status,
            'starts_at' => $startsAt?->toDateTimeString() ?? '',
            'ends_at' => $endsAt?->toDateTimeString() ?? '',
            'days_remaining' => $daysRemaining,
            'cancelled_at' => $tenant->cancelled_at?->toDateTimeString(),
            'cancellation_reason' => $tenant->cancellation_reason,
            'created_at' => $latest?->created_at?->toDateTimeString() ?? $tenant->created_at?->toDateTimeString() ?? '',
            'updated_at' => $tenant->updated_at?->toDateTimeString() ?? '',
            'plan' => $plan ? [
                'id' => $plan->id,
                'title' => $plan->name,
                'description' => $plan->description,
                'price' => number_format((float) $plan->price, 2, '.', ''),
                'days' => (int) ($plan->duration_days ?? 30),
                'currency' => $plan->currency ?? 'EGP',
                'currency_symbol' => \App\Support\PlanCurrency::symbol($plan->currency ?? 'EGP'),
                'billing_cycle' => $plan->billing_cycle ?? 'monthly',
            ] : null,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
            ],
        ];
    }
}
