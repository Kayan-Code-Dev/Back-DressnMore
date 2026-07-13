<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Providers;

use App\Services\Intelligence\DressnMoreAiClient;
use Illuminate\Support\Facades\Log;

class IntelligenceProviderManager
{
    private ?IntelligenceProviderInterface $primary = null;
    private ?IntelligenceProviderInterface $fallback = null;

    public function __construct()
    {
        $this->resolveProviders();
    }

    private function resolveProviders(): void
    {
        $providerName = config('intelligence.provider', 'local');
        $externalEnabled = config('intelligence.external_enabled', false);

        // Primary provider
        if ($externalEnabled && $providerName === 'groq') {
            try {
                $this->primary = new GroqIntelligenceProvider();
                Log::info('Intelligence primary provider resolved', ['provider' => 'groq', 'model' => $this->primary->model()]);
            } catch (\Throwable $e) {
                Log::warning('Groq provider failed to initialize, falling back to local', ['error' => $e->getMessage()]);
                $this->primary = null;
            }
        }

        // Fallback to local
        if ($this->primary === null) {
            try {
                $this->primary = new LocalIntelligenceProvider(new DressnMoreAiClient());
                Log::info('Intelligence primary provider resolved', ['provider' => 'local']);
            } catch (\Throwable $e) {
                Log::error('Local provider also failed to initialize', ['error' => $e->getMessage()]);
            }
        }
    }

    public function primary(): ?IntelligenceProviderInterface
    {
        return $this->primary;
    }

    public function fallback(): ?IntelligenceProviderInterface
    {
        return $this->fallback;
    }

    public function isExternal(): bool
    {
        return $this->primary instanceof GroqIntelligenceProvider;
    }

    public function health(): array
    {
        $primaryHealth = $this->primary?->health() ?? ['status' => 'not_initialized'];
        $fallbackHealth = $this->fallback?->health() ?? ['status' => 'not_configured'];

        return [
            'primary' => [
                'provider' => $this->primary?->name(),
                'model' => $this->primary?->model(),
                'health' => $primaryHealth,
            ],
            'fallback' => [
                'provider' => $this->fallback?->name(),
                'model' => $this->fallback?->model(),
                'health' => $fallbackHealth,
            ],
        ];
    }
}
