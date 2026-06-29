<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\Platform\SubscriptionResource;
use App\Models\Central\Payment;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Platform\TenantSubscriptionAdminService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private readonly TenantSubscriptionAdminService $adminService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $paginator = $this->adminService->paginate([
            'status' => $request->query('status'),
            'search' => $request->query('search'),
        ], $perPage);

        $rows = collect($paginator->items())
            ->map(fn (Tenant $tenant) => $this->adminService->present($tenant))
            ->all();

        return ApiResponse::paginated($paginator, $rows);
    }

    public function show(int $id): JsonResponse
    {
        $subscription = Subscription::with(['plan', 'tenant'])->find($id);
        if ($subscription !== null) {
            $payments = Payment::query()
                ->where('tenant_id', $subscription->tenant_id)
                ->latest('id')
                ->limit(20)
                ->get();

            return ApiResponse::success(array_merge(
                (new SubscriptionResource($subscription))->resolve(),
                ['payments' => $payments->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'amount' => number_format((float) $p->amount, 2, '.', ''),
                    'status' => $p->status,
                    'paid_at' => $p->paid_at?->toDateTimeString(),
                    'reference' => $p->reference,
                    'purpose' => $p->purpose,
                ])->all()],
            ));
        }

        $tenant = Tenant::query()->with('plan')->findOrFail($id);

        return ApiResponse::success(array_merge(
            $this->adminService->present($tenant),
            ['payments' => Payment::query()
                ->where('tenant_id', $tenant->id)
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'amount' => number_format((float) $p->amount, 2, '.', ''),
                    'status' => $p->status,
                    'paid_at' => $p->paid_at?->toDateTimeString(),
                    'reference' => $p->reference,
                    'purpose' => $p->purpose,
                ])->all()],
        ));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $subscription = Subscription::find($id);
        $validated = $request->validate([
            'status' => ['required', 'in:pending,active,cancelled,rejected,expired'],
        ]);

        if ($subscription !== null) {
            $subscription->update(['status' => $validated['status']]);
            if ($validated['status'] === 'cancelled' && $subscription->tenant) {
                $subscription->tenant->update([
                    'cancelled_at' => now(),
                ]);
            }

            return ApiResponse::success(new SubscriptionResource($subscription->refresh()->load(['plan', 'tenant'])), 'Subscription updated');
        }

        $tenant = Tenant::query()->with('plan')->findOrFail($id);
        if ($validated['status'] === 'cancelled') {
            $tenant->update(['cancelled_at' => now()]);
        }

        return ApiResponse::success($this->adminService->present($tenant->refresh()), 'Subscription updated');
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $tenant = Tenant::query()->find($id);
        if ($tenant === null) {
            $subscription = Subscription::query()->findOrFail($id);
            $tenant = $subscription->tenant;
        }

        if ($tenant === null) {
            return ApiResponse::error('Tenant not found', 404);
        }

        $tenant->update([
            'cancelled_at' => now(),
            'cancellation_reason' => $validated['cancellation_reason'] ?? null,
        ]);

        Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        return ApiResponse::success($this->adminService->present($tenant->refresh()->load('plan')), 'تم إلغاء الاشتراك');
    }
}
