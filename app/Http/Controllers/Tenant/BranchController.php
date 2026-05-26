<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Branch\StoreBranchRequest;
use App\Http\Requests\Tenant\Branch\UpdateBranchRequest;
use App\Http\Resources\Tenant\BranchResource;
use App\Services\Tenant\BranchService;
use App\Support\ApiResponse;
use App\Support\CsvExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BranchController extends Controller
{
    public function __construct(private readonly BranchService $branchService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $branches = $this->branchService->paginate([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'city_id' => $request->query('city_id'),
            'currency_id' => $request->query('currency_id'),
        ], $perPage);

        return ApiResponse::paginated($branches, BranchResource::collection($branches->items())->resolve());
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = $this->branchService->create($request->validated());

        return ApiResponse::success(new BranchResource($branch), 'Branch created', 201);
    }

    public function show(int $branch): JsonResponse
    {
        $branchModel = $this->branchService->findOrFail($branch);

        return ApiResponse::success(new BranchResource($branchModel));
    }

    public function update(UpdateBranchRequest $request, int $branch): JsonResponse
    {
        $branchModel = $this->branchService->findOrFail($branch);
        $branchModel = $this->branchService->update($branchModel, $request->validated());

        return ApiResponse::success(new BranchResource($branchModel), 'Branch updated');
    }

    public function destroy(int $branch): JsonResponse
    {
        $branchModel = $this->branchService->findOrFail($branch);
        $this->branchService->delete($branchModel);

        return ApiResponse::success(null, 'Branch deleted');
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->branchService->exportRows([
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'city_id' => $request->query('city_id'),
            'currency_id' => $request->query('currency_id'),
        ]);

        return CsvExporter::download(
            filename: 'branches.csv',
            headers: ['ID', 'Branch Code', 'Name', 'Phone', 'Currency', 'Address', 'Status'],
            rows: $rows
        );
    }
}
