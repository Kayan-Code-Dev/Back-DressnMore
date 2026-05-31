<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Tailoring\UpdateTailoringMeasurementsRequest;
use App\Services\Tenant\TailoringOrderService;
use App\Support\ApiResponse;
use App\Support\Tenant\TailoringOrderPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TailoringOrderController extends Controller
{
    public function __construct(private readonly TailoringOrderService $tailoringOrderService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $orders = $this->tailoringOrderService->paginate([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
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

    public function show(int $invoice): JsonResponse
    {
        $order = $this->tailoringOrderService->findOrFail($invoice);

        return ApiResponse::success(TailoringOrderPresenter::fromInvoice($order, includeDetails: true));
    }

    public function updateMeasurements(UpdateTailoringMeasurementsRequest $request, int $invoice): JsonResponse
    {
        $order = $this->tailoringOrderService->findOrFail($invoice);
        $order = $this->tailoringOrderService->updateMeasurements($order, $request->validated('measurements'));

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
