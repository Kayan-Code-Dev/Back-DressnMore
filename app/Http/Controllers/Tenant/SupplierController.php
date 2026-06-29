<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Supplier\StoreSupplierRequest;
use App\Http\Requests\Tenant\Supplier\UpdateSupplierRequest;
use App\Http\Resources\Tenant\SupplierResource;
use App\Services\Tenant\SupplierAccountService;
use App\Services\Tenant\SupplierService;
use App\Enums\ReportExportFormat;
use App\Support\ApiResponse;
use App\Support\CsvExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $supplierService,
        private readonly SupplierAccountService $supplierAccountService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $suppliers = $this->supplierService->paginate([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
        ], $perPage);

        return ApiResponse::paginated($suppliers, SupplierResource::collection($suppliers->items())->resolve());
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = $this->supplierService->create($request->validated());

        return ApiResponse::success(new SupplierResource($supplier), 'Supplier created', 201);
    }

    public function show(int $supplier): JsonResponse
    {
        $supplierModel = $this->supplierService->findOrFail($supplier);

        return ApiResponse::success(new SupplierResource($supplierModel));
    }

    public function account(int $supplier): JsonResponse
    {
        $supplierModel = $this->supplierAccountService->findSupplierOrFail($supplier);

        return ApiResponse::success($this->supplierAccountService->summary($supplierModel));
    }

    public function exportAccount(Request $request, int $supplier): StreamedResponse|Response|JsonResponse
    {
        $formatValue = strtolower(trim((string) $request->query('format', 'xlsx')));
        $format = ReportExportFormat::tryFrom($formatValue);

        if ($format === null) {
            return ApiResponse::error('صيغة التصدير غير مدعومة', 422);
        }

        $supplierModel = $this->supplierAccountService->findSupplierOrFail($supplier);

        return $this->supplierAccountService->exportStatement($supplierModel, $format);
    }

    public function update(UpdateSupplierRequest $request, int $supplier): JsonResponse
    {
        $supplierModel = $this->supplierService->findOrFail($supplier);
        $supplierModel = $this->supplierService->update($supplierModel, $request->validated());

        return ApiResponse::success(new SupplierResource($supplierModel), 'Supplier updated');
    }

    public function destroy(int $supplier): JsonResponse
    {
        $supplierModel = $this->supplierService->findOrFail($supplier);
        $this->supplierService->delete($supplierModel);

        return ApiResponse::success(null, 'Supplier deleted');
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->supplierService->exportRows([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
        ]);

        return CsvExporter::download(
            filename: 'suppliers.csv',
            headers: ['ID', 'Code', 'Name', 'Phone', 'Address', 'Status', 'Current Balance', 'Remaining'],
            rows: $rows
        );
    }
}
