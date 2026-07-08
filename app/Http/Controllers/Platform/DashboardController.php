<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\SubscriptionDashboardStatsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly SubscriptionDashboardStatsService $statsService) {}

    public function subscriptionStats(): JsonResponse
    {
        return ApiResponse::success($this->statsService->stats());
    }
}
