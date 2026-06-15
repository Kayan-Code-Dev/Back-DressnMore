<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\JournalEntry\CancelJournalEntryRequest;
use App\Http\Requests\Tenant\JournalEntry\StoreJournalEntryRequest;
use App\Http\Requests\Tenant\JournalEntry\UpdateJournalEntryRequest;
use App\Http\Resources\Tenant\JournalEntryResource;
use App\Models\Tenant\Account;
use App\Services\Tenant\JournalEntryService;
use App\Support\ApiResponse;
use App\Support\Reports\TabularExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JournalEntryController extends Controller
{
    public function __construct(private readonly JournalEntryService $journalEntryService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $paginator = $this->journalEntryService->paginate($this->filters($request), $perPage);

        return ApiResponse::paginated(
            $paginator,
            JournalEntryResource::collection($paginator->items())->resolve(),
        );
    }

    public function summary(Request $request): JsonResponse
    {
        return ApiResponse::success($this->journalEntryService->summary($this->filters($request)));
    }

    public function store(StoreJournalEntryRequest $request): JsonResponse
    {
        $entry = $this->journalEntryService->create($request->validated(), $request->user()?->id);

        return ApiResponse::success(new JournalEntryResource($entry), 'Journal entry created', 201);
    }

    public function show(int $journalEntry): JsonResponse
    {
        $entry = $this->journalEntryService->findOrFail($journalEntry);

        return ApiResponse::success(new JournalEntryResource($entry));
    }

    public function update(UpdateJournalEntryRequest $request, int $journalEntry): JsonResponse
    {
        $entry = $this->journalEntryService->findOrFail($journalEntry);
        $entry = $this->journalEntryService->update($entry, $request->validated(), $request->user()?->id);

        return ApiResponse::success(new JournalEntryResource($entry), 'Journal entry updated');
    }

    public function approve(Request $request, int $journalEntry): JsonResponse
    {
        $entry = $this->journalEntryService->findOrFail($journalEntry);
        $entry = $this->journalEntryService->approve($entry, $request->user()?->id);

        return ApiResponse::success(new JournalEntryResource($entry), 'Journal entry approved');
    }

    public function cancel(CancelJournalEntryRequest $request, int $journalEntry): JsonResponse
    {
        $entry = $this->journalEntryService->findOrFail($journalEntry);
        $entry = $this->journalEntryService->cancel(
            $entry,
            $request->validated('cancellation_reason'),
            $request->user()?->id,
        );

        return ApiResponse::success(new JournalEntryResource($entry), 'Journal entry cancelled');
    }

    public function reverse(Request $request, int $journalEntry): JsonResponse
    {
        $entry = $this->journalEntryService->findOrFail($journalEntry);
        $reversal = $this->journalEntryService->reverse($entry, $request->user()?->id);

        return ApiResponse::success(new JournalEntryResource($reversal), 'Reversal journal entry created', 201);
    }

    public function export(Request $request): StreamedResponse|Response
    {
        $rows = $this->journalEntryService->exportRows($this->filters($request));
        $headers = [
            'entry_number',
            'entry_date',
            'type',
            'source_type',
            'reference_number',
            'description',
            'total_debit',
            'total_credit',
            'difference',
            'status',
            'branch',
            'created_by',
        ];

        return TabularExport::download(
            $request->query('format'),
            'journal-entries',
            'القيود المحاسبية',
            $headers,
            $rows,
        );
    }

    public function accounts(): JsonResponse
    {
        $accounts = Account::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        return ApiResponse::success($accounts);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'search' => $request->query('search'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'status' => $request->query('status'),
            'type' => $request->query('type'),
            'source_type' => $request->query('source_type'),
            'branch_id' => $request->query('branch_id'),
            'account_id' => $request->query('account_id'),
            'is_balanced' => $request->query('is_balanced'),
        ];
    }
}
