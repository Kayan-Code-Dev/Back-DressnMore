<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Expense\StoreExpenseRequest;
use App\Http\Requests\Tenant\Expense\UpdateExpenseRequest;
use App\Http\Resources\Tenant\ExpenseResource;
use App\Services\Tenant\ExpenseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenseService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $expenses = $this->expenseService->paginate([
            'search' => $request->query('search'),
            'expense_category_id' => $request->query('expense_category_id'),
            'method' => $request->query('method'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ], $perPage);

        return ApiResponse::paginated($expenses, ExpenseResource::collection($expenses->items())->resolve());
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenseService->create($request->validated(), $request->user()?->id);

        return ApiResponse::success(new ExpenseResource($expense), 'Expense created', 201);
    }

    public function show(int $expense): JsonResponse
    {
        $expenseModel = $this->expenseService->findOrFail($expense);

        return ApiResponse::success(new ExpenseResource($expenseModel));
    }

    public function update(UpdateExpenseRequest $request, int $expense): JsonResponse
    {
        $expenseModel = $this->expenseService->findOrFail($expense);
        $expenseModel = $this->expenseService->update($expenseModel, $request->validated(), $request->user()?->id);

        return ApiResponse::success(new ExpenseResource($expenseModel), 'Expense updated');
    }

    public function destroy(int $expense): JsonResponse
    {
        $expenseModel = $this->expenseService->findOrFail($expense);
        $this->expenseService->delete($expenseModel);

        return ApiResponse::success(null, 'Expense deleted');
    }
}
