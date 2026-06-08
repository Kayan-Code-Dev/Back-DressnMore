<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Product\StoreProductRequest;
use App\Http\Requests\Tenant\Product\UpdateProductRequest;
use App\Http\Resources\Tenant\ProductResource;
use App\Services\Tenant\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $products = $this->productService->paginate([
            'search' => $request->query('search'),
            'branch_id' => $request->query('branch_id'),
            'is_active' => $request->query('is_active'),
        ], $perPage);

        return ApiResponse::paginated($products, ProductResource::collection($products->items())->resolve());
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create($request->validated(), $request->user()?->id);

        return ApiResponse::success(new ProductResource($product), 'Product created', 201);
    }

    public function show(int $product): JsonResponse
    {
        return ApiResponse::success(new ProductResource($this->productService->findOrFail($product)));
    }

    public function update(UpdateProductRequest $request, int $product): JsonResponse
    {
        $productModel = $this->productService->findOrFail($product);
        $productModel = $this->productService->update($productModel, $request->validated());

        return ApiResponse::success(new ProductResource($productModel), 'Product updated');
    }

    public function destroy(int $product): JsonResponse
    {
        $productModel = $this->productService->findOrFail($product);
        $this->productService->delete($productModel);

        return ApiResponse::success(null, 'Product deleted');
    }
}
