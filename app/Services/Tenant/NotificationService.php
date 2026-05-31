<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class NotificationService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 15, ?int $userId = null): LengthAwarePaginator
    {
        $query = Notification::query()->latest('id');

        if ($userId !== null) {
            $query->where(function (Builder $builder) use ($userId): void {
                $builder->whereNull('user_id')->orWhere('user_id', $userId);
            });
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder->whereRaw('LOWER(title) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(message) LIKE ?', [$needle]);
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return array{total:int,read:int,unread:int}
     */
    public function stats(?int $userId = null): array
    {
        $query = Notification::query();

        if ($userId !== null) {
            $query->where(function (Builder $builder) use ($userId): void {
                $builder->whereNull('user_id')->orWhere('user_id', $userId);
            });
        }

        $total = (clone $query)->count();
        $read = (clone $query)->whereNotNull('read_at')->count();

        return [
            'total' => $total,
            'read' => $read,
            'unread' => max(0, $total - $read),
        ];
    }

    public function findOrFail(int $notificationId): Notification
    {
        return Notification::query()->findOrFail($notificationId);
    }

    public function markRead(Notification $notification): Notification
    {
        $notification->read_at = Carbon::now();
        $notification->save();

        return $notification->refresh();
    }

    public function markAllRead(?int $userId = null): int
    {
        $query = Notification::query()->whereNull('read_at');

        if ($userId !== null) {
            $query->where(function (Builder $builder) use ($userId): void {
                $builder->whereNull('user_id')->orWhere('user_id', $userId);
            });
        }

        return $query->update(['read_at' => Carbon::now()]);
    }

    public function delete(Notification $notification): void
    {
        $notification->delete();
    }
}
