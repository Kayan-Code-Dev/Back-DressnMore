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
}
