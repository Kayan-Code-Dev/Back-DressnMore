<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\NotificationService;
use App\Support\ApiResponse;
use App\Support\Tenant\HrOperationsPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $userId = $request->user()?->id;
        $paginator = $this->notificationService->paginate([
            'search' => $request->query('search'),
            'category' => $request->query('category'),
            'unread_only' => $request->boolean('unread_only'),
        ], $perPage, $userId);

        $data = collect($paginator->items())
            ->map(fn ($row) => HrOperationsPresenter::notification($row))
            ->all();

        return ApiResponse::success($data, 'Success', 200, [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'stats' => $this->notificationService->stats($userId),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        return ApiResponse::success($this->notificationService->stats($request->user()?->id));
    }

    public function markRead(int $notification): JsonResponse
    {
        $model = $this->notificationService->findOrFail($notification);
        $model = $this->notificationService->markRead($model);

        return ApiResponse::success(HrOperationsPresenter::notification($model), 'Notification marked as read');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllRead($request->user()?->id);

        return ApiResponse::success(['updated' => $count], 'All notifications marked as read');
    }

    public function destroy(int $notification): JsonResponse
    {
        $model = $this->notificationService->findOrFail($notification);
        $this->notificationService->delete($model);

        return ApiResponse::success(null, 'Notification deleted');
    }
}
