<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Tailoring\ChangeTailoringStageRequest;
use App\Http\Requests\Tenant\Tailoring\StoreTailoringOrderRequest;
use App\Http\Requests\Tenant\Tailoring\UpdateTailoringMeasurementsRequest;
use App\Http\Requests\Tenant\Tailoring\UpdateTailoringOrderRequest;
use App\Services\Auth\TenantAuthService;
use App\Services\Tenant\TailoringOrderService;
use App\Services\Tenant\TailoringProductionService;
use App\Support\ApiResponse;
use App\Support\Tenant\TailoringOrderPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TailoringOrderController extends Controller
{
    public function __construct(
        private readonly TailoringOrderService $tailoringOrderService,
        private readonly TailoringProductionService $tailoringProductionService,
        private readonly TenantAuthService $tenantAuthService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $orders = $this->tailoringOrderService->paginate([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'production_stage' => $request->query('production_stage'),
            'production_status' => $request->query('production_status'),
            'stage' => $request->query('stage'),
            'priority' => $request->query('priority'),
            'assigned_tailor_id' => $request->query('assigned_tailor_id'),
            'branch_id' => $request->query('branch_id'),
            'customer_id' => $request->query('customer_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ], $perPage);

        $rows = collect($orders->items())
            ->map(fn ($invoice) => TailoringOrderPresenter::fromInvoice($invoice))
            ->values()
            ->all();

        return ApiResponse::paginated($orders, $rows);
    }

    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->tailoringOrderService->stats());
    }

    public function store(StoreTailoringOrderRequest $request): JsonResponse
    {
        $order = $this->tailoringProductionService->create(
            $request->validated(),
            $request->user()?->id,
        );

        return ApiResponse::success(
            TailoringOrderPresenter::fromInvoice($order, includeDetails: true),
            'Tailoring order created',
            201,
        );
    }

    public function show(int $invoice): JsonResponse
    {
        $order = $this->tailoringOrderService->findOrFail($invoice);
        $order->load(['tailoringStageHistories.changedByUser']);

        return ApiResponse::success(TailoringOrderPresenter::fromInvoice($order, includeDetails: true));
    }

    public function update(UpdateTailoringOrderRequest $request, int $invoice): JsonResponse
    {
        $order = $this->tailoringOrderService->findOrFail($invoice);
        $order = $this->tailoringProductionService->update(
            $order,
            $request->validated(),
            $request->user()?->id,
        );

        return ApiResponse::success(
            TailoringOrderPresenter::fromInvoice($order, includeDetails: true),
            'Tailoring order updated',
        );
    }

    public function changeStage(ChangeTailoringStageRequest $request, int $invoice): JsonResponse
    {
        $order = $this->tailoringOrderService->findOrFail($invoice);
        $user = $request->user();
        $permissions = $user !== null ? $this->tenantAuthService->permissionsForUser($user) : [];
        $canOverride = in_array('tailoring.override_stage', $permissions, true);

        $order = $this->tailoringProductionService->changeStage(
            $order,
            $request->validated(),
            $user?->id,
            $canOverride,
        );

        return ApiResponse::success(
            TailoringOrderPresenter::fromInvoice($order, includeDetails: true),
            'Stage updated',
        );
    }

    public function stageHistory(int $invoice): JsonResponse
    {
        $order = $this->tailoringOrderService->findOrFail($invoice);

        return ApiResponse::success($this->tailoringProductionService->stageHistory($order));
    }

    public function workshopBoard(Request $request): JsonResponse
    {
        return ApiResponse::success($this->tailoringProductionService->workshopBoard([
            'search' => $request->query('search'),
            'branch_id' => $request->query('branch_id'),
            'priority' => $request->query('priority'),
        ]));
    }

    public function schedule(Request $request): JsonResponse
    {
        return ApiResponse::success($this->tailoringProductionService->schedule([
            'branch_id' => $request->query('branch_id'),
        ]));
    }

    public function updateMeasurements(UpdateTailoringMeasurementsRequest $request, int $invoice): JsonResponse
    {
        $order = $this->tailoringOrderService->findOrFail($invoice);
        $order = $this->tailoringOrderService->updateMeasurements(
            $order,
            $request->validated('measurements'),
            $request->user()?->id,
        );

        return ApiResponse::success(TailoringOrderPresenter::fromInvoice($order, includeDetails: true), 'Measurements updated');
    }

    public function deliveries(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $rows = $this->tailoringOrderService->paginateDeliveries([
            'search' => $request->query('search'),
        ], $perPage);

        return ApiResponse::paginated($rows, $rows->items());
    }
}
