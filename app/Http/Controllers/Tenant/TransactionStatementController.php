<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ReportExportFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Cashbox\CloseStatementPeriodRequest;
use App\Services\Tenant\TransactionStatementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionStatementController extends Controller
{
    public function __construct(private readonly TransactionStatementService $statementService) {}

    public function branches(Request $request): JsonResponse
    {
        return ApiResponse::success($this->statementService->branchSummaries($this->filters($request)));
    }

    public function summary(Request $request): JsonResponse
    {
        return ApiResponse::success($this->statementService->summary($this->filters($request)));
    }

    public function ledger(Request $request): JsonResponse
    {
        return ApiResponse::success($this->statementService->ledger($this->filters($request)));
    }

    public function closePeriod(CloseStatementPeriodRequest $request): JsonResponse
    {
        try {
            $result = $this->statementService->closePeriod(
                $request->validated(),
                $request->user()?->id,
            );
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success($result, 'تم إقفال الفترة بنجاح');
    }

    public function export(Request $request): StreamedResponse|Response|JsonResponse
    {
        $formatValue = strtolower(trim((string) $request->query('format', 'xlsx')));
        $format = ReportExportFormat::tryFrom($formatValue);

        if ($format === null) {
            return ApiResponse::error('Unsupported export format', 422);
        }

        return $this->statementService->export($this->filters($request), $format);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'search' => $request->query('search'),
            'type' => $request->query('type'),
            'movement_type' => $request->query('movement_type'),
            'branch_id' => $request->query('branch_id'),
            'category' => $request->query('category'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];
    }
}
