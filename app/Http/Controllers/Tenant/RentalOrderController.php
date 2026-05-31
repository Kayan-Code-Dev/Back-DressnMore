<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\RentalOrderService;
use App\Support\ApiResponse;
use App\Support\Tenant\RentalOrderPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RentalOrderController extends Controller
{
    public function __construct(private readonly RentalOrderService $rentalOrderService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $orders = $this->rentalOrderService->paginate([
            'search' => $request->query('search'),
            'client_name' => $request->query('client_name'),
            'status' => $request->query('status'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ], $perPage);

        $rows = collect($orders->items())
            ->map(fn ($invoice) => RentalOrderPresenter::fromInvoice($invoice))
            ->values()
            ->all();

        return ApiResponse::paginated($orders, $rows);
    }

    public function stats(Request $request): JsonResponse
    {
        $stats = $this->rentalOrderService->stats([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ]);

        return ApiResponse::success($stats);
    }

    public function show(int $invoice): JsonResponse
    {
        $order = $this->rentalOrderService->findOrFail($invoice);

        return ApiResponse::success(RentalOrderPresenter::fromInvoice($order, includeDetails: true));
    }
}
