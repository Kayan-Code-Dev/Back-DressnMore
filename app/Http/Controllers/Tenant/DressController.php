<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Dress\StoreDressRequest;
use App\Http\Requests\Tenant\Dress\UpdateDressRequest;
use App\Http\Resources\Tenant\DressResource;
use App\Http\Resources\Tenant\InventoryMovementResource;
use App\Services\Tenant\DressService;
use App\Services\Tenant\InventoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DressController extends Controller
{
    public function __construct(
        private readonly DressService $dressService,
        private readonly InventoryService $inventoryService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $dresses = $this->dressService->paginate([
            'search' => $request->query('search'),
            'dress_category_id' => $request->query('dress_category_id'),
            'dress_subcategory_id' => $request->query('dress_subcategory_id'),
            'branch_id' => $request->query('branch_id'),
            'status' => $request->query('status'),
            'color' => $request->query('color'),
            'size' => $request->query('size'),
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

    public function inventoryMovements(Request $request, int $dress): JsonResponse
    {
        $dressModel = $this->dressService->findOrFail($dress);
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $movements = $this->inventoryService->paginateForDress($dressModel, $perPage);

        return ApiResponse::paginated($movements, InventoryMovementResource::collection($movements->items())->resolve());
    }
}
