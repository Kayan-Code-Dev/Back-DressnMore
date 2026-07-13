<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Providers;

use App\Services\Intelligence\DressnMoreAiClient;

class LocalIntelligenceProvider implements IntelligenceProviderInterface
{
    private DressnMoreAiClient $client;

    public function __construct(DressnMoreAiClient $client)
    {
        $this->client = $client;
    }

    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        $start = hrtime(true);
        $result = $this->client->generate($messages, $options);
        $elapsedMs = (int) ((hrtime(true) - $start) / 1e6);

        return [
            'response' => $result['response'] ?? '',
            'tool_calls' => [],
            'input_tokens' => $result['input_tokens'] ?? 0,
            'output_tokens' => $result['output_tokens'] ?? 0,
            'total_tokens' => $result['total_tokens'] ?? 0,
            'generation_time_ms' => $result['generation_time_ms'] ?? $elapsedMs,
            'provider' => $this->name(),
            'model' => 'local-qwen2.5-0.5b',
        ];
    }

    public function health(): array
    {
        return $this->client->health();
    }

    public function name(): string
    {
        return 'local';
    }

    public function model(): string
    {
        return 'local-qwen2.5-0.5b';
    }
}
