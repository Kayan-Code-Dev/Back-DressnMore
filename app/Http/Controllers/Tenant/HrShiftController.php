<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Hr\Shift\StoreHrShiftRequest;
use App\Http\Requests\Tenant\Hr\Shift\UpdateHrShiftRequest;
use App\Http\Resources\Tenant\HrShiftResource;
use App\Services\Tenant\HrShiftService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrShiftController extends Controller
{
    public function __construct(private readonly HrShiftService $hrShiftService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $shifts = $this->hrShiftService->paginate(
            branchId: $request->integer('branch_id') ?: null,
            status: $request->query('status'),
            perPage: $perPage,
        );

        return ApiResponse::paginated($shifts, HrShiftResource::collection($shifts->items())->resolve());
    }

    public function store(StoreHrShiftRequest $request): JsonResponse
    {
        $shift = $this->hrShiftService->create($request->validated());

        return ApiResponse::success(new HrShiftResource($shift), 'Shift created', 201);
    }

    public function show(int $shift): JsonResponse
    {
        return ApiResponse::success(new HrShiftResource($this->hrShiftService->findOrFail($shift)));
    }

    public function update(UpdateHrShiftRequest $request, int $shift): JsonResponse
    {
        $shiftModel = $this->hrShiftService->findOrFail($shift);
        $shiftModel = $this->hrShiftService->update($shiftModel, $request->validated());

        return ApiResponse::success(new HrShiftResource($shiftModel), 'Shift updated');
    }

    public function destroy(int $shift): JsonResponse
    {
        $this->hrShiftService->delete($this->hrShiftService->findOrFail($shift));

        return ApiResponse::success(null, 'Shift deleted');
    }
}
