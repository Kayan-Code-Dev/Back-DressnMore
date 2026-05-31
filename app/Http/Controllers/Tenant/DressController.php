<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Dress\StoreDressRequest;
use App\Http\Requests\Tenant\Dress\TransferDressRequest;
use App\Http\Requests\Tenant\Dress\UpdateDressRequest;
use App\Http\Resources\Tenant\DressOrderHistoryResource;
use App\Http\Resources\Tenant\DressResource;
use App\Http\Resources\Tenant\InventoryMovementResource;
use App\Services\Tenant\DressService;
use App\Services\Tenant\InventoryService;
use App\Support\ApiResponse;
use App\Support\CsvExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DressController extends Controller
{
    public function __construct(
        private readonly DressService $dressService,
        private readonly InventoryService $inventoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $dresses = $this->dressService->paginate([
            'id' => $request->query('id'),
            'search' => $request->query('search'),
            'name' => $request->query('name'),
            'code' => $request->query('code'),
            'dress_category_id' => $request->query('dress_category_id'),
            'dress_subcategory_id' => $request->query('dress_subcategory_id'),
            'category_id' => $request->query('category_id'),
            'subcat_id' => $request->query('subcat_id'),
            'branch_id' => $request->query('branch_id'),
            'entity_type' => $request->query('entity_type'),
            'entity_id' => $request->query('entity_id'),
            'status' => $request->query('status'),
            'color' => $request->query('color'),
            'size' => $request->query('size'),
            'created_from' => $request->query('created_from'),
            'created_to' => $request->query('created_to'),
            'delivery_date' => $request->query('delivery_date'),
            'days_of_rent' => $request->query('days_of_rent'),
            'occasion_datetime' => $request->query('occasion_datetime'),
            'visit_datetime' => $request->query('visit_datetime'),
        ], $perPage);

        return ApiResponse::paginated($dresses, DressResource::collection($dresses->items())->resolve());
    }

    public function store(StoreDressRequest $request): JsonResponse
    {
        $dress = $this->dressService->create(
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return ApiResponse::success(new DressResource($dress), 'Dress created', 201);
    }

    public function show(int $dress): JsonResponse
    {
        $dressModel = $this->dressService->findOrFail($dress);

        return ApiResponse::success(new DressResource($dressModel));
    }

    public function update(UpdateDressRequest $request, int $dress): JsonResponse
    {
        $dressModel = $this->dressService->findOrFail($dress);
        $dressModel = $this->dressService->update(
            dress: $dressModel,
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return ApiResponse::success(new DressResource($dressModel), 'Dress updated');
    }

    public function destroy(int $dress): JsonResponse
    {
        $dressModel = $this->dressService->findOrFail($dress);
        $this->dressService->delete($dressModel);

        return ApiResponse::success(null, 'Dress deleted');
    }

    public function transfer(TransferDressRequest $request, int $dress): JsonResponse
    {
        $dressModel = $this->dressService->findOrFail($dress);
        $dressModel = $this->dressService->transferToBranch(
            dress: $dressModel,
            toBranchId: $request->integer('to_branch_id'),
            notes: $request->string('notes')->toString() ?: null,
            actorId: $request->user()?->id,
        );

        return ApiResponse::success(new DressResource($dressModel), 'Dress transferred');
    }

    public function inventoryMovements(Request $request, int $dress): JsonResponse
    {
        $dressModel = $this->dressService->findOrFail($dress);
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $movements = $this->inventoryService->paginateForDress($dressModel, $perPage);

        return ApiResponse::paginated($movements, InventoryMovementResource::collection($movements->items())->resolve());
    }

    public function orderHistory(Request $request, int $dress): JsonResponse
    {
        $dressModel = $this->dressService->findOrFail($dress);
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $history = $this->dressService->orderHistory($dressModel, $perPage);

        return ApiResponse::paginated($history, DressOrderHistoryResource::collection($history->items())->resolve());
    }

    public function availableForDate(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $dresses = $this->dressService->availableForDate([
            'date' => $request->query('date'),
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'branch_id' => $request->query('branch_id'),
            'category_id' => $request->query('category_id'),
            'subcat_id' => $request->query('subcat_id'),
            'status' => $request->query('status'),
        ], $perPage);

        return ApiResponse::paginated($dresses, DressResource::collection($dresses->items())->resolve());
    }

    public function unavailableDays(int $dress): JsonResponse
    {
        $dressModel = $this->dressService->findOrFail($dress);
        $data = $this->dressService->unavailableDays($dressModel);

        return ApiResponse::success($data);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->dressService->exportRows([
            'id' => $request->query('id'),
            'search' => $request->query('search'),
            'name' => $request->query('name'),
            'code' => $request->query('code'),
            'dress_category_id' => $request->query('dress_category_id'),
            'dress_subcategory_id' => $request->query('dress_subcategory_id'),
            'category_id' => $request->query('category_id'),
            'subcat_id' => $request->query('subcat_id'),
            'branch_id' => $request->query('branch_id'),
            'entity_type' => $request->query('entity_type'),
            'entity_id' => $request->query('entity_id'),
            'status' => $request->query('status'),
            'created_from' => $request->query('created_from'),
            'created_to' => $request->query('created_to'),
            'delivery_date' => $request->query('delivery_date'),
            'days_of_rent' => $request->query('days_of_rent'),
            'occasion_datetime' => $request->query('occasion_datetime'),
            'visit_datetime' => $request->query('visit_datetime'),
        ]);

        return CsvExporter::download(
            filename: 'dresses.csv',
            headers: ['ID', 'Code', 'Name', 'Status', 'Branch', 'Category', 'Subcategory', 'Entity Type', 'Entity ID', 'Rental Price', 'Sale Price', 'Created At'],
            rows: $rows,
        );
    }
}
