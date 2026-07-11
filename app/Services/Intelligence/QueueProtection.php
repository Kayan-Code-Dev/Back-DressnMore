<?php

namespace App\Services\Intelligence;

use App\Models\Tenant\Intelligence\AiRun;
use App\Services\Tenant\TenantContext;
use RuntimeException;

class QueueProtection
{
    private TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    /**
     * Check if a user can start a new AI run.
     *
     * @throws RuntimeException
     */
    public function canStartRun(int $userId): void
    {
        $maxActivePerUser = config('intelligence.queue.max_active_runs_per_user', 1);
        $maxPendingPerTenant = config('intelligence.queue.max_pending_runs_per_tenant', 5);

        // Check active runs for this user
        $activeUserRuns = AiRun::forUser($userId)
            ->active()
            ->count();

        if ($activeUserRuns >= $maxActivePerUser) {
            throw new RuntimeException('You already have an AI request in progress. Please wait for it to complete.');
        }

        // Check pending runs for this tenant (all users)
        $pendingTenantRuns = AiRun::pending()->count();

        if ($pendingTenantRuns >= $maxPendingPerTenant) {
            throw new RuntimeException('AI assistant queue is at capacity for this workspace. Please try again shortly.');
        }
    }

    /**
     * Get queue status for a user.
     */
    public function getUserQueueStatus(int $userId): array
    {
        return [
            'active_runs' => AiRun::forUser($userId)->active()->count(),
            'max_active' => config('intelligence.queue.max_active_runs_per_user', 1),
            'pending_tenant' => AiRun::pending()->count(),
            'max_pending_tenant' => config('intelligence.queue.max_pending_runs_per_tenant', 5),
        ];
    }
}
