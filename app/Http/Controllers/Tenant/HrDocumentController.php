<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Hr\Document\StoreHrDocumentRequest;
use App\Http\Requests\Tenant\Hr\Document\UpdateHrDocumentRequest;
use App\Http\Resources\Tenant\HrDocumentResource;
use App\Services\Tenant\HrDocumentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrDocumentController extends Controller
{
    public function __construct(private readonly HrDocumentService $hrDocumentService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $documents = $this->hrDocumentService->paginate([
            'employee_id' => $request->query('employee_id'),
            'document_type' => $request->query('document_type'),
            'status' => $request->query('status'),
            'expires_before' => $request->query('expires_before'),
        ], $perPage);

        return ApiResponse::paginated($documents, HrDocumentResource::collection($documents->items())->resolve());
    }

    public function store(StoreHrDocumentRequest $request): JsonResponse
    {
        $document = $this->hrDocumentService->create(
            $request->validated(),
            $request->user()?->id,
        );

        return ApiResponse::success(new HrDocumentResource($document), 'Document created', 201);
    }

    public function show(int $document): JsonResponse
    {
        return ApiResponse::success(new HrDocumentResource($this->hrDocumentService->findOrFail($document)));
    }

    public function update(UpdateHrDocumentRequest $request, int $document): JsonResponse
    {
        $documentModel = $this->hrDocumentService->findOrFail($document);
        $documentModel = $this->hrDocumentService->update($documentModel, $request->validated());

        return ApiResponse::success(new HrDocumentResource($documentModel), 'Document updated');
    }

    public function destroy(int $document): JsonResponse
    {
        $this->hrDocumentService->delete($this->hrDocumentService->findOrFail($document));

        return ApiResponse::success(null, 'Document deleted');
    }

    public function expiryAlerts(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $documents = $this->hrDocumentService->expiryAlerts([
            'employee_id' => $request->query('employee_id'),
            'document_type' => $request->query('document_type'),
        ], $perPage);

        return ApiResponse::paginated($documents, HrDocumentResource::collection($documents->items())->resolve());
    }
}
