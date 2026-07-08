<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DressCategory\StoreDressCategoryRequest;
use App\Http\Requests\Tenant\DressCategory\UpdateDressCategoryRequest;
use App\Http\Resources\Tenant\DressCategoryResource;
use App\Services\Tenant\DressCategoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DressCategoryController extends Controller
{
    public function __construct(private readonly DressCategoryService $dressCategoryService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $categories = $this->dressCategoryService->paginate(
            search: $request->query('search'),
            status: $request->query('status'),
            parentId: $request->query('parent_id'),
            onlyParents: $request->boolean('only_parents'),
            onlyChildren: $request->boolean('only_children'),
            perPage: $perPage,
        );

        return ApiResponse::paginated($categories, DressCategoryResource::collection($categories->items())->resolve());
    }

    public function store(StoreDressCategoryRequest $request): JsonResponse
    {
        $category = $this->dressCategoryService->create($request->validated());

        return ApiResponse::success(new DressCategoryResource($category), 'Dress category created', 201);
    }

    public function show(int $dressCategory): JsonResponse
    {
        $category = $this->dressCategoryService->findOrFail($dressCategory);

        return ApiResponse::success(new DressCategoryResource($category));
    }

    public function update(UpdateDressCategoryRequest $request, int $dressCategory): JsonResponse
    {
        $category = $this->dressCategoryService->findOrFail($dressCategory);
        $category = $this->dressCategoryService->update($category, $request->validated());

        return ApiResponse::success(new DressCategoryResource($category), 'Dress category updated');
    }

    public function destroy(int $dressCategory): JsonResponse
    {
        $category = $this->dressCategoryService->findOrFail($dressCategory);
        $this->dressCategoryService->delete($category);

        return ApiResponse::success(null, 'Dress category deleted');
    }

    public function tree(Request $request): JsonResponse
    {
        $categories = \App\Models\Tenant\DressCategory::whereNull('parent_id')
            ->with('children.children')
            ->where('status', 'active')
            ->get();

        return ApiResponse::success($categories);
    }
}
