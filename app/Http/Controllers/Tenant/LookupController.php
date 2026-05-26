<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\LookupService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class LookupController extends Controller
{
    public function __construct(private readonly LookupService $lookupService) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->lookupService->all());
    }
}
