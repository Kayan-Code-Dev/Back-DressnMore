<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Hr\Attendance\StoreHrAttendanceRequest;
use App\Http\Requests\Tenant\Hr\Attendance\UpdateHrAttendanceRequest;
use App\Http\Resources\Tenant\HrAttendanceRecordResource;
use App\Services\Tenant\HrAttendanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrAttendanceController extends Controller
{
    public function __construct(private readonly HrAttendanceService $hrAttendanceService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $records = $this->hrAttendanceService->paginate([
            'date' => $request->query('date'),
            'status' => $request->query('status'),
            'employee_id' => $request->query('employee_id'),
            'branch_id' => $request->query('branch_id'),
        ], $perPage);

        return ApiResponse::paginated($records, HrAttendanceRecordResource::collection($records->items())->resolve());
    }

    public function store(StoreHrAttendanceRequest $request): JsonResponse
    {
        $record = $this->hrAttendanceService->create($request->validated());

        return ApiResponse::success(new HrAttendanceRecordResource($record), 'Attendance saved', 201);
    }

    public function update(UpdateHrAttendanceRequest $request, int $attendance): JsonResponse
    {
        $record = $this->hrAttendanceService->findOrFail($attendance);
        $record = $this->hrAttendanceService->update($record, $request->validated());

        return ApiResponse::success(new HrAttendanceRecordResource($record), 'Attendance updated');
    }
}
