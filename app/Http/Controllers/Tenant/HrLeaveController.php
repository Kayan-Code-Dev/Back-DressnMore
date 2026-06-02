<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Hr\Leave\StoreHrLeaveRequest;
use App\Http\Requests\Tenant\Hr\Leave\UpdateHrLeaveStatusRequest;
use App\Http\Resources\Tenant\HrLeaveRequestResource;
use App\Services\Tenant\HrLeaveService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrLeaveController extends Controller
{
    public function __construct(private readonly HrLeaveService $hrLeaveService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $leaves = $this->hrLeaveService->paginate([
            'status' => $request->query('status'),
            'type' => $request->query('type'),
            'employee_id' => $request->query('employee_id'),
        ], $perPage);

        return ApiResponse::paginated($leaves, HrLeaveRequestResource::collection($leaves->items())->resolve());
    }

    public function store(StoreHrLeaveRequest $request): JsonResponse
    {
        $leave = $this->hrLeaveService->create($request->validated());

        return ApiResponse::success(new HrLeaveRequestResource($leave), 'Leave request created', 201);
    }

    public function updateStatus(UpdateHrLeaveStatusRequest $request, int $leave): JsonResponse
    {
        $leaveModel = $this->hrLeaveService->findOrFail($leave);
        $leaveModel = $this->hrLeaveService->updateStatus(
            $leaveModel,
            $request->validated(),
            (int) $request->user()->id,
        );

        return ApiResponse::success(new HrLeaveRequestResource($leaveModel), 'Leave status updated');
    }
}
