<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\Subscription;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * List all subscriptions with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));

        $query = Subscription::with(['plan'])
            ->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $subscriptions = $query->paginate($perPage);

        return ApiResponse::paginated($subscriptions, $subscriptions->items());
    }

    /**
     * Show single subscription with payments.
     */
    public function show(int $id): JsonResponse
    {
        $subscription = Subscription::with(['plan', 'payments'])->findOrFail($id);
        return ApiResponse::success($subscription);
    }

    /**
     * Update subscription status.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,active,cancelled'],
        ]);

        $subscription->update(['status' => $validated['status']]);

        return ApiResponse::success($subscription, 'Subscription updated');
    }
}

