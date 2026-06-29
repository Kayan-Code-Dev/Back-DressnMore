<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Notification;
use App\Models\Tenant\User;
use Illuminate\Support\Collection;

class TenantNotifier
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function toUser(
        int $userId,
        string $title,
        string $message,
        string $category = 'system',
        string $priority = 'normal',
        ?string $actionUrl = null,
    ): Notification {
        return $this->notificationService->create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'category' => $category,
            'priority' => $priority,
            'action_url' => $actionUrl,
        ]);
    }

    public function broadcast(
        string $title,
        string $message,
        string $category = 'system',
        string $priority = 'normal',
        ?string $actionUrl = null,
    ): Notification {
        return $this->notificationService->create([
            'user_id' => null,
            'title' => $title,
            'message' => $message,
            'category' => $category,
            'priority' => $priority,
            'action_url' => $actionUrl,
        ]);
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    public function toUsersWithPermissions(
        array $permissionKeys,
        string $title,
        string $message,
        string $category = 'system',
        string $priority = 'normal',
        ?string $actionUrl = null,
        ?int $exceptUserId = null,
    ): void {
        $this->userIdsWithPermissions($permissionKeys, $exceptUserId)
            ->each(fn (int $userId) => $this->toUser($userId, $title, $message, $category, $priority, $actionUrl));
    }

    /**
     * @param  list<string>  $permissionKeys
     * @return Collection<int, int>
     */
    private function userIdsWithPermissions(array $permissionKeys, ?int $exceptUserId = null): Collection
    {
        $query = User::query()
            ->where('status', 'active')
            ->whereHas('roles.permissions', function ($builder) use ($permissionKeys): void {
                $builder->whereIn('key', $permissionKeys);
            });

        if ($exceptUserId) {
            $query->where('id', '!=', $exceptUserId);
        }

        return $query->pluck('id');
    }
}
