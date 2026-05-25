<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\Subscription;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    public function index(): JsonResponse
    {
        $subscriptions = Subscription::query()
            ->with(['tenant', 'plan'])
            ->latest('id')
            ->paginate(20);

        return ApiResponse::paginated($subscriptions);
    }
}
