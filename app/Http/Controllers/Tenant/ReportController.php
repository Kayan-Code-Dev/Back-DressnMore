<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ReportExportFormat;
use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Services\Tenant\ReportService;
use App\Support\ApiResponse;
use App\Support\ReportDateRange;
use App\Support\Reports\ReportCatalog;
use App\Support\Reports\ReportExporter;
use App\Support\Reports\ReportTabularizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function catalog(): JsonResponse
    {
        return ApiResponse::success([
            'reports' => ReportCatalog::all(),
            'export_formats' => ReportExportFormat::values(),
        ]);
    }

    public function overview(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportService->overview($this->filters($request)));
    }

    public function sales(Request $request): JsonResponse|Response
    {
        if ($request->query('export') !== null) {
            return $this->show($request, 'sales');
        }

        return ApiResponse::success($this->reportService->salesSummary($this->filters($request)));
    }

    public function tailoring(Request $request): JsonResponse|Response
    {
        if ($request->query('export') !== null) {
            return $this->show($request, 'tailoring');
        }

        return ApiResponse::success($this->reportService->tailoringSummary($this->filters($request)));
    }

    public function show(Request $request, string $type): JsonResponse|Response
    {
        $definition = ReportCatalog::find($type);
        if ($definition === null) {
            return ApiResponse::error('Report not found', 404);
        }

        $user = $request->user();
        if ($user instanceof User && ! $this->userHasReportPermission($user, $definition['permission'])) {
            return ApiResponse::forbidden();
        }

        $filters = $this->filters($request);

        if ($request->query('export') !== null) {
            return $this->export($request, $type, $definition, $filters);
        }

        return ApiResponse::success($this->reportService->resolve($type, $filters));
    }

    /**
     * @param  array{key: string, label: string, label_ar: string, permission: string, group: string}  $definition
     * @param  array<string, mixed>  $filters
     */
    private function export(Request $request, string $type, array $definition, array $filters): Response
    {
        $validated = $request->validate([
            'export' => ['required', 'string', Rule::in(ReportExportFormat::values())],
        ]);

        $format = ReportExportFormat::from($validated['export']);
        $payload = $this->reportService->resolve($type, $filters);
        $tabular = ReportTabularizer::fromReport($type, $this->tabularPayload($type, $payload));

        return ReportExporter::download(
            $format,
            $type.'-'.now()->format('Ymd-His'),
            $definition['label_ar'],
            $tabular['headers'],
            $tabular['rows'],
            [
                'from' => $filters['date_from'] ?? ReportDateRange::resolve($filters)['from'],
                'to' => $filters['date_to'] ?? ReportDateRange::resolve($filters)['to'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    private function tabularPayload(string $type, array $payload): array
    {
        if (in_array($type, ['sales-daily', 'sales-products', 'sales-employees'], true)) {
            return $payload['items'] ?? [];
        }

        if ($type === 'suppliers') {
            return $payload;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'period' => $request->query('period'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'branch_id' => $request->query('branch_id'),
        ];
    }

    private function userHasReportPermission(User $user, string $permissionKey): bool
    {
        return $user->roles()
            ->whereHas('permissions', fn ($query) => $query->where('key', $permissionKey))
            ->exists();
    }
}
