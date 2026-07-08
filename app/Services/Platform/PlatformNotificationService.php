<?php

namespace App\Services\Platform;

use App\Models\Central\PlatformNotification;
use App\Models\Central\SuperAdmin;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PlatformNotificationService
{
    public function create(
        string $title,
        string $message,
        string $category = 'system',
        string $priority = 'normal',
        ?string $actionUrl = null,
        ?int $superAdminId = null,
    ): PlatformNotification {
        return PlatformNotification::query()->create([
            'super_admin_id' => $superAdminId,
            'title' => $title,
            'message' => $message,
            'category' => $category,
            'priority' => $priority,
            'action_url' => $actionUrl,
        ]);
    }

    public function notifyAllAdmins(
        string $title,
        string $message,
        string $category = 'system',
        string $priority = 'normal',
        ?string $actionUrl = null,
    ): void {
        $admins = SuperAdmin::query()->where('status', 'active')->pluck('id');

        if ($admins->isEmpty()) {
            $this->create($title, $message, $category, $priority, $actionUrl);

            return;
        }

        foreach ($admins as $adminId) {
            $this->create($title, $message, $category, $priority, $actionUrl, (int) $adminId);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage, ?int $adminId): LengthAwarePaginator
    {
        $query = PlatformNotification::query()->latest('id');
        $this->scopeForAdmin($query, $adminId);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder->whereRaw('LOWER(title) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(message) LIKE ?', [$needle]);
            });
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $query->where('category', $category);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return array{total:int,read:int,unread:int}
     */
    public function stats(?int $adminId): array
    {
        $query = PlatformNotification::query();
        $this->scopeForAdmin($query, $adminId);

        $total = (clone $query)->count();
        $read = (clone $query)->whereNotNull('read_at')->count();

        return [
            'total' => $total,
            'read' => $read,
            'unread' => max(0, $total - $read),
        ];
    }

    public function markRead(PlatformNotification $notification): PlatformNotification
    {
        $notification->read_at = Carbon::now();
        $notification->save();

        return $notification->refresh();
    }

    public function markAllRead(?int $adminId): int
    {
        $query = PlatformNotification::query()->whereNull('read_at');
        $this->scopeForAdmin($query, $adminId);

        return $query->update(['read_at' => Carbon::now()]);
    }

    public function delete(PlatformNotification $notification): void
    {
        $notification->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(PlatformNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'category' => $notification->category,
            'priority' => $notification->priority,
            'read_at' => $notification->read_at?->toDateTimeString(),
            'created_at' => $notification->created_at?->toDateTimeString() ?? '',
            'action_url' => $notification->action_url,
        ];
    }

    private function scopeForAdmin(Builder $query, ?int $adminId): void
    {
        if ($adminId === null) {
            return;
        }

        $query->where(function (Builder $builder) use ($adminId): void {
            $builder->whereNull('super_admin_id')->orWhere('super_admin_id', $adminId);
        });
    }
}
