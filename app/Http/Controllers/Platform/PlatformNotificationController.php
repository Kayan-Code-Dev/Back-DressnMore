<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\PlatformNotification;
use App\Services\Platform\PlatformNotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformNotificationController extends Controller
{
    public function __construct(private readonly PlatformNotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $adminId = $request->user('sanctum')?->id;
        $paginator = $this->notificationService->paginate([
            'search' => $request->query('search'),
            'category' => $request->query('category'),
        ], $perPage, $adminId);

        $rows = collect($paginator->items())
            ->map(fn (PlatformNotification $row) => $this->notificationService->present($row))
            ->all();

        return ApiResponse::success($rows, 'Success', 200, [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'stats' => $this->notificationService->stats($adminId),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        return ApiResponse::success($this->notificationService->stats($request->user('sanctum')?->id));
    }

    public function markRead(PlatformNotification $notification): JsonResponse
    {
        $notification = $this->notificationService->markRead($notification);

        return ApiResponse::success($this->notificationService->present($notification), 'Notification marked as read');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllRead($request->user('sanctum')?->id);

        return ApiResponse::success(['updated' => $count], 'All notifications marked as read');
    }

    public function destroy(PlatformNotification $notification): JsonResponse
    {
        $this->notificationService->delete($notification);

        return ApiResponse::success(null, 'Notification deleted');
    }
}
