<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ExpenseCategory\StoreExpenseCategoryRequest;
use App\Http\Requests\Tenant\ExpenseCategory\UpdateExpenseCategoryRequest;
use App\Http\Resources\Tenant\ExpenseCategoryResource;
use App\Services\Tenant\ExpenseCategoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function __construct(private readonly ExpenseCategoryService $expenseCategoryService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $categories = $this->expenseCategoryService->paginate(
            search: $request->query('search'),
            status: $request->query('status'),
            perPage: $perPage,
        );

        return ApiResponse::paginated($categories, ExpenseCategoryResource::collection($categories->items())->resolve());
    }

    public function store(StoreExpenseCategoryRequest $request): JsonResponse
    {
        $category = $this->expenseCategoryService->create($request->validated());

        return ApiResponse::success(new ExpenseCategoryResource($category), 'Expense category created', 201);
    }

    public function show(int $expenseCategory): JsonResponse
    {
        $category = $this->expenseCategoryService->findOrFail($expenseCategory);

        return ApiResponse::success(new ExpenseCategoryResource($category));
    }

    public function update(UpdateExpenseCategoryRequest $request, int $expenseCategory): JsonResponse
    {
        $category = $this->expenseCategoryService->findOrFail($expenseCategory);
        $category = $this->expenseCategoryService->update($category, $request->validated());

        return ApiResponse::success(new ExpenseCategoryResource($category), 'Expense category updated');
    }

    public function destroy(int $expenseCategory): JsonResponse
    {
        $category = $this->expenseCategoryService->findOrFail($expenseCategory);
        $this->expenseCategoryService->delete($category);

        return ApiResponse::success(null, 'Expense category deleted');
    }
}
