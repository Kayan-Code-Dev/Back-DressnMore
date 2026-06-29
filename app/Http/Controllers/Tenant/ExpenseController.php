<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Expense\ApproveExpenseRequest;
use App\Http\Requests\Tenant\Expense\CancelExpenseRequest;
use App\Http\Requests\Tenant\Expense\PayExpenseRequest;
use App\Http\Requests\Tenant\Expense\StoreExpenseRequest;
use App\Http\Requests\Tenant\Expense\UpdateExpenseRequest;
use App\Http\Resources\Tenant\ExpenseResource;
use App\Services\Tenant\ExpenseService;
use App\Support\ApiResponse;
use App\Support\Reports\TabularExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenseService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $expenses = $this->expenseService->paginate([
            'search' => $request->query('search'),
            'expense_category_id' => $request->query('expense_category_id'),
            'branch_id' => $request->query('branch_id'),
            'cashbox_id' => $request->query('cashbox_id'),
            'status' => $request->query('status'),
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

    public function approve(ApproveExpenseRequest $request, int $expense): JsonResponse
    {
        $expenseModel = $this->expenseService->findOrFail($expense);
        $expenseModel = $this->expenseService->approve($expenseModel, $request->user()?->id);

        return ApiResponse::success(new ExpenseResource($expenseModel), 'Expense approved');
    }

    public function cancel(CancelExpenseRequest $request, int $expense): JsonResponse
    {
        $expenseModel = $this->expenseService->findOrFail($expense);
        $expenseModel = $this->expenseService->cancel($expenseModel, $request->validated('notes'));

        return ApiResponse::success(new ExpenseResource($expenseModel), 'Expense cancelled');
    }

    public function pay(PayExpenseRequest $request, int $expense): JsonResponse
    {
        $expenseModel = $this->expenseService->findOrFail($expense);
        $expenseModel = $this->expenseService->pay(
            expense: $expenseModel,
            data: $request->validated(),
            actorId: $request->user()?->id,
        );

        return ApiResponse::success(new ExpenseResource($expenseModel), 'Expense paid');
    }

    public function summary(Request $request): JsonResponse
    {
        $summary = $this->expenseService->summary([
            'expense_category_id' => $request->query('expense_category_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ]);

        return ApiResponse::success($summary);
    }

    public function export(Request $request): StreamedResponse|Response
    {
        $rows = $this->expenseService->exportRows([
            'search' => $request->query('search'),
            'expense_category_id' => $request->query('expense_category_id'),
            'branch_id' => $request->query('branch_id'),
            'cashbox_id' => $request->query('cashbox_id'),
            'status' => $request->query('status'),
            'method' => $request->query('method'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ]);

        $headers = ['ID', 'Category', 'Branch', 'Cashbox', 'Status', 'Amount', 'Method', 'Vendor', 'Reference Number', 'Expense Date', 'Transaction ID'];

        return TabularExport::download(
            $request->query('format'),
            'expenses',
            'المصروفات',
            $headers,
            $rows,
        );
    }
}
