<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\HrEmployeeAccountService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HrAccessController extends Controller
{
    public function __construct(private readonly HrEmployeeAccountService $accountService) {}

    public function roles(): JsonResponse
    {
        return ApiResponse::success($this->accountService->listRoles());
    }

    public function permissions(): JsonResponse
    {
        return ApiResponse::success($this->accountService->listPermissions());
    }
}
