<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\WorkshopService;
use App\Support\ApiResponse;
use App\Support\Tenant\HrOperationsPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkshopController extends Controller
{
    public function __construct(private readonly WorkshopService $workshopService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $rows = $this->workshopService->paginate(['search' => $request->query('search')], $perPage);
        $data = collect($rows->items())->map(fn ($row) => HrOperationsPresenter::workshop($row))->all();

        return ApiResponse::paginated($rows, $data);
    }

    public function show(int $workshop): JsonResponse
    {
        return ApiResponse::success(HrOperationsPresenter::workshop($this->workshopService->findOrFail($workshop)));
    }

    public function transfers(Request $request, int $workshop): JsonResponse
    {
        $this->workshopService->findOrFail($workshop);
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $rows = $this->workshopService->paginateTransfers(['workshop_id' => $workshop], $perPage);
        $data = collect($rows->items())->map(fn ($row) => HrOperationsPresenter::workshopTransfer($row))->all();

        return ApiResponse::paginated($rows, $data);
    }

    public function cloths(Request $request, int $workshop): JsonResponse
    {
        $this->workshopService->findOrFail($workshop);
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $rows = $this->workshopService->paginateCloths(['workshop_id' => $workshop], $perPage);
        $data = collect($rows->items())->map(fn ($row) => HrOperationsPresenter::workshopCloth($row))->all();

        return ApiResponse::paginated($rows, $data);
    }
}
