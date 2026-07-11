<?php

namespace App\Jobs\Intelligence;

use App\Models\Central\Tenant;
use App\Models\Tenant\Intelligence\AiRun;
use App\Services\Intelligence\AiOrchestrator;
use App\Services\Tenant\TenantContext;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAiChatRun implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $timeout = 150;
    public int $backoff = 10;

    private int $tenantId;

    public function __construct(
        private readonly int $runId,
    ) {
        $this->onQueue(config('intelligence.queue.queue_name', 'intelligence'));

        // Capture the current tenant ID
        $tenant = app(TenantContext::class)->tenant();
        $this->tenantId = $tenant?->id ?? 0;
    }

    public function handle(AiOrchestrator $orchestrator, TenantContext $tenantContext, TenantDatabaseManager $dbManager): void
    {
        if ($this->tenantId === 0) {
            Log::warning('AI run job has no tenant ID', ['run_id' => $this->runId]);
            return;
        }

        // Resolve and connect to tenant
        $tenant = Tenant::find($this->tenantId);
        if ($tenant === null) {
            Log::warning('AI run tenant not found', ['run_id' => $this->runId, 'tenant_id' => $this->tenantId]);
            return;
        }

        $tenantContext->setTenant($tenant);
        $dbManager->connect($tenant);

        // Now find the run in the correct tenant DB
        $run = AiRun::find($this->runId);

        if ($run === null) {
            Log::warning('AI run not found', ['run_id' => $this->runId, 'tenant' => $tenant->slug]);
            return;
        }

        if ($run->status !== 'pending') {
            Log::info('AI run already processed', ['run_id' => $this->runId, 'status' => $run->status]);
            return;
        }

        $orchestrator->executeRun($run);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('AI run job failed permanently', [
            'run_id' => $this->runId,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);

        // Try to mark the run as failed if we can connect to the tenant
        try {
            if ($this->tenantId > 0) {
                $tenant = Tenant::find($this->tenantId);
                if ($tenant) {
                    $dbManager = app(TenantDatabaseManager::class);
                    $dbManager->connect($tenant);
                    $run = AiRun::find($this->runId);
                    if ($run !== null && $run->status !== 'completed') {
                        $run->markFailed('Processing failed after retries: ' . $exception->getMessage());
                    }
                }
            }
        } catch (Throwable $e) {
            Log::error('Could not mark AI run as failed', ['error' => $e->getMessage()]);
        }
    }
}
