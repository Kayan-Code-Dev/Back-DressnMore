<?php

namespace App\Http\Controllers\Tenant;

use App\Models\Tenant\Cashbox;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Cashbox\StoreCashboxRequest;
use App\Http\Requests\Tenant\Cashbox\UpdateCashboxRequest;
use App\Http\Resources\Tenant\CashboxResource;
use App\Http\Resources\Tenant\CashMovementResource;
use App\Services\Tenant\CashboxService;
use App\Support\ApiResponse;
use App\Support\Reports\TabularExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashboxController extends Controller
{
    public function __construct(private readonly CashboxService $cashboxService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $cashboxes = $this->cashboxService->paginate([
            'search' => $request->query('search'),
            'branch_id' => $request->query('branch_id'),
            'is_active' => $request->query('is_active'),
        ], $perPage);

        return ApiResponse::paginated($cashboxes, CashboxResource::collection($cashboxes->items())->resolve());
    }

    public function store(StoreCashboxRequest $request): JsonResponse
    {
        $cashbox = $this->cashboxService->create($request->validated());

        return ApiResponse::success(new CashboxResource($cashbox), 'Cashbox created', 201);
    }

    public function show(int $cashbox): JsonResponse
    {
        $cashboxModel = Cashbox::query()
            ->with('branch')
            ->withSum(['movements as total_in' => fn ($q) => $q->where('direction', 'in')->where('is_reversed', false)], 'amount')
            ->withSum(['movements as total_out' => fn ($q) => $q->where('direction', 'out')->where('is_reversed', false)], 'amount')
            ->findOrFail($cashbox);

        return ApiResponse::success(new CashboxResource($cashboxModel));
    }

    public function update(UpdateCashboxRequest $request, int $cashbox): JsonResponse
    {
        $cashboxModel = $this->cashboxService->findOrFail($cashbox);
        $cashboxModel = $this->cashboxService->update($cashboxModel, $request->validated());

        return ApiResponse::success(new CashboxResource($cashboxModel), 'Cashbox updated');
    }

    public function destroy(int $cashbox): JsonResponse
    {
        $cashboxModel = $this->cashboxService->findOrFail($cashbox);
        $this->cashboxService->delete($cashboxModel);

        return ApiResponse::success(null, 'Cashbox deleted');
    }

    public function transactions(Request $request, int $cashbox): JsonResponse
    {
        $cashboxModel = $this->cashboxService->findOrFail($cashbox);
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $transactions = $this->cashboxService->transactions($cashboxModel, $perPage);

        return ApiResponse::paginated(
            $transactions,
            CashMovementResource::collection($transactions->items())->resolve()
        );
    }

    public function recalculate(int $cashbox): JsonResponse
    {
        $cashboxModel = $this->cashboxService->findOrFail($cashbox);
        $cashboxModel = $this->cashboxService->recalculate($cashboxModel);

        return ApiResponse::success(new CashboxResource($cashboxModel), 'Cashbox recalculated');
    }

    public function export(Request $request): StreamedResponse|Response
    {
        $rows = $this->cashboxService->exportRows([
            'search' => $request->query('search'),
            'branch_id' => $request->query('branch_id'),
            'is_active' => $request->query('is_active'),
        ]);
        $headers = ['ID', 'Name', 'Branch ID', 'Initial Balance', 'Current Balance', 'Status'];

        return TabularExport::download(
            $request->query('format'),
            'cashboxes',
            'الخزائن',
            $headers,
            $rows,
        );
    }

    public function dailySummary(Request $request): JsonResponse
    {
        $summary = $this->cashboxService->dailySummary([
            'cashbox_id' => $request->query('cashbox_id'),
            'branch_id' => $request->query('branch_id'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ]);

        return ApiResponse::success($summary);
    }
}
