<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Hr\JobTitle\StoreHrJobTitleRequest;
use App\Http\Requests\Tenant\Hr\JobTitle\UpdateHrJobTitleRequest;
use App\Http\Resources\Tenant\HrJobTitleResource;
use App\Services\Tenant\HrJobTitleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrJobTitleController extends Controller
{
    public function __construct(private readonly HrJobTitleService $hrJobTitleService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $jobTitles = $this->hrJobTitleService->paginate(
            search: $request->query('search'),
            departmentId: $request->filled('department_id') ? $request->integer('department_id') : null,
            status: $request->query('status'),
            perPage: $perPage,
        );

        return ApiResponse::paginated($jobTitles, HrJobTitleResource::collection($jobTitles->items())->resolve());
    }

    public function store(StoreHrJobTitleRequest $request): JsonResponse
    {
        $jobTitle = $this->hrJobTitleService->create($request->validated());

        return ApiResponse::success(new HrJobTitleResource($jobTitle), 'Job title created', 201);
    }

    public function show(int $jobTitle): JsonResponse
    {
        return ApiResponse::success(new HrJobTitleResource($this->hrJobTitleService->findOrFail($jobTitle)));
    }

    public function update(UpdateHrJobTitleRequest $request, int $jobTitle): JsonResponse
    {
        $jobTitleModel = $this->hrJobTitleService->findOrFail($jobTitle);
        $jobTitleModel = $this->hrJobTitleService->update($jobTitleModel, $request->validated());

        return ApiResponse::success(new HrJobTitleResource($jobTitleModel), 'Job title updated');
    }

    public function destroy(int $jobTitle): JsonResponse
    {
        $this->hrJobTitleService->delete($this->hrJobTitleService->findOrFail($jobTitle));

        return ApiResponse::success(null, 'Job title deleted');
    }
}
