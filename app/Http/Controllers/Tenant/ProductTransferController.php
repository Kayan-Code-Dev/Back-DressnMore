<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ProductTransfer\RejectProductTransferRequest;
use App\Http\Requests\Tenant\ProductTransfer\StoreProductTransferRequest;
use App\Http\Resources\Tenant\ProductTransferResource;
use App\Services\Tenant\ProductTransferService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductTransferController extends Controller
{
    public function __construct(private readonly ProductTransferService $productTransferService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $rows = $this->productTransferService->paginate([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'from_branch_id' => $request->query('from_branch_id'),
            'to_branch_id' => $request->query('to_branch_id'),
        ], $perPage);

        return ApiResponse::paginated($rows, ProductTransferResource::collection($rows->items())->resolve());
    }

    public function store(StoreProductTransferRequest $request): JsonResponse
    {
        $transfer = $this->productTransferService->create($request->validated(), $request->user()?->id);

        return ApiResponse::success(new ProductTransferResource($transfer), 'Product transfer created', 201);
    }

    public function confirm(int $productTransfer, Request $request): JsonResponse
    {
        $transfer = $this->productTransferService->findOrFail($productTransfer);
        $transfer = $this->productTransferService->confirm($transfer, $request->user()?->id);

        return ApiResponse::success(new ProductTransferResource($transfer), 'Product transfer confirmed');
    }

    public function reject(RejectProductTransferRequest $request, int $productTransfer): JsonResponse
    {
        $transfer = $this->productTransferService->findOrFail($productTransfer);
        $transfer = $this->productTransferService->reject(
            transfer: $transfer,
            reason: $request->validated()['rejection_reason'] ?? null,
            actorId: $request->user()?->id,
        );

        return ApiResponse::success(new ProductTransferResource($transfer), 'Product transfer rejected');
    }

    public function destroy(int $productTransfer): JsonResponse
    {
        $transfer = $this->productTransferService->findOrFail($productTransfer);
        $this->productTransferService->delete($transfer);

        return ApiResponse::success(null, 'Product transfer deleted');
    }
}
