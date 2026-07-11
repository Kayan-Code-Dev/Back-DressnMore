<?php

namespace App\Console\Commands\Intelligence;

use App\Services\Intelligence\DressnMoreAiClient;
use Illuminate\Console\Command;

class IntelligenceHealth extends Command
{
    protected $signature = 'intelligence:health';
    protected $description = 'Check AI service and queue worker health';

    public function handle(DressnMoreAiClient $client): int
    {
        $this->info('DressnMore Intelligence Health Check');
        $this->newLine();

        $health = $client->health();
        if ($health['status'] === 'healthy') {
            $this->info("  AI Service: HEALTHY");
            if (isset($health['details']['model'])) {
                $this->info("  Model: {$health['details']['model']}");
            }
        } else {
            $this->error("  AI Service: {$health['status']}");
        }

        $this->newLine();

        $result = shell_exec('systemctl is-active dressnmore-ai-worker 2>/dev/null') ?? 'unknown';
        $status = trim($result);
        if ($status === 'active') {
            $this->info("  Queue Worker: RUNNING");
        } else {
            $this->error("  Queue Worker: {$status}");
        }

        $this->newLine();
        $this->info('  Queue: ' . config('intelligence.queue.queue_name', 'intelligence'));
        $this->info('  Service URL: ' . config('intelligence.service.base_url'));

        return 0;
    }
}
