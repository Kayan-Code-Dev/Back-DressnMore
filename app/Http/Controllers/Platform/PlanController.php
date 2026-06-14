<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\Plan\StorePlanRequest;
use App\Http\Requests\Platform\Plan\UpdatePlanRequest;
use App\Http\Resources\Platform\PlanResource;
use App\Models\Central\Plan;
use App\Services\Platform\PlanService;
use App\Support\ApiResponse;
use App\Support\PlanFeatureCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PlanController extends Controller
{
    public function __construct(private readonly PlanService $planService) {}

    public function featureCatalog(): JsonResponse
    {
        return ApiResponse::success([
            'features' => PlanFeatureCatalog::definitions(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $plans = $this->planService->paginate([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
        ], $perPage);

        return ApiResponse::paginated(
            $plans,
            PlanResource::collection($plans->items())->resolve(),
        );
    }

    public function show(Plan $plan): JsonResponse
    {
        return ApiResponse::success(new PlanResource($plan->load(['features'])->loadCount('tenants')));
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = $this->planService->create($request->validated());

        return ApiResponse::success(new PlanResource($plan->loadCount('tenants')), 'Plan created', 201);
    }

    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        $plan = $this->planService->update($plan, $request->validated());

        return ApiResponse::success(new PlanResource($plan->loadCount('tenants')), 'Plan updated');
    }

    public function destroy(Plan $plan): JsonResponse
    {
        try {
            $this->planService->destroy($plan);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success(null, 'Plan deleted');
    }

    public function publicIndex(Request $request): JsonResponse
    {
        $perPage = max(1, min(50, $request->integer('per_page', 15)));
        $page = max(1, $request->integer('page', 1));

        $plans = Plan::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($plans->items())->map(function (Plan $plan): array {
            return [
                'id' => $plan->id,
                'title' => $plan->name,
                'description' => $plan->description ?? '',
                'price' => number_format((float) $plan->price, 2, '.', ''),
                'days' => (int) ($plan->duration_days ?? 30),
                'is_active' => $plan->status === 'active',
                'max_branches' => 1,
                'max_employees' => 999,
                'max_dresses' => 999,
                'max_invoices_monthly' => 999,
                'created_at' => $plan->created_at?->toIso8601String(),
                'updated_at' => $plan->updated_at?->toIso8601String(),
            ];
        })->all();

        return response()->json([
            'success' => true,
            'current_page' => $plans->currentPage(),
            'data' => $items,
            'first_page_url' => $plans->url(1),
            'from' => $plans->firstItem(),
            'last_page' => $plans->lastPage(),
            'last_page_url' => $plans->url($plans->lastPage()),
            'links' => $plans->linkCollection()->toArray(),
            'next_page_url' => $plans->nextPageUrl(),
            'path' => $plans->path(),
            'per_page' => $plans->perPage(),
            'prev_page_url' => $plans->previousPageUrl(),
            'to' => $plans->lastItem(),
            'total' => $plans->total(),
        ]);
    }
}
