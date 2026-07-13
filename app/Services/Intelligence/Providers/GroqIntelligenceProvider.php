<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GroqIntelligenceProvider implements IntelligenceProviderInterface
{
    private string $baseUrl;
    private string $apiKey;
    private string $model;
    private int $timeout;
    private int $connectTimeout;
    private int $maxOutputTokens;
    private int $maxToolRounds;
    private int $maxToolsPerRun;

    public function __construct(
        string $model = null,
        int $maxToolRounds = 3,
        int $maxToolsPerRun = 6,
    ) {
        $this->baseUrl = rtrim(config('intelligence.groq.base_url', 'https://api.groq.com/openai/v1'), '/');
        $this->apiKey = config('intelligence.groq.api_key', '');
        $this->model = $model ?? config('intelligence.groq.primary_model', 'qwen/qwen3-32b');
        $this->timeout = config('intelligence.groq.timeout', 25);
        $this->connectTimeout = config('intelligence.groq.connect_timeout', 5);
        $this->maxOutputTokens = config('intelligence.groq.max_output_tokens', 800);
        $this->maxToolRounds = $maxToolRounds;
        $this->maxToolsPerRun = $maxToolsPerRun;

        if (empty($this->apiKey)) {
            throw new RuntimeException('Groq API key is not configured.');
        }
    }

    public function chat(array $messages, array $tools = [], array $options = []): array
    {
        $start = hrtime(true);
        $requestedModel = $options['model'] ?? $this->model;
        $maxTokens = min($options['max_tokens'] ?? $this->maxOutputTokens, $this->maxOutputTokens);

        $payload = [
            'model' => $requestedModel,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.3,
            'max_tokens' => $maxTokens,
            'stream' => false,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->post("{$this->baseUrl}/chat/completions", $payload);

            if ($response->status() === 429) {
                Log::warning('Groq rate limited', ['model' => $requestedModel]);
                throw new RuntimeException('GROQ_RATE_LIMITED');
            }

            if ($response->status() === 401 || $response->status() === 403) {
                Log::error('Groq auth rejected');
                throw new RuntimeException('GROQ_AUTH_FAILED');
            }

            if ($response->failed()) {
                Log::error('Groq API error', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                throw new RuntimeException('GROQ_API_ERROR');
            }

            $data = $response->json();
            $choice = $data['choices'][0] ?? null;
            $message = $choice['message'] ?? [];

            $elapsedMs = (int) ((hrtime(true) - $start) / 1e6);

            return [
                'response' => $message['content'] ?? '',
                'tool_calls' => $message['tool_calls'] ?? [],
                'input_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $data['usage']['total_tokens'] ?? 0,
                'generation_time_ms' => $elapsedMs,
                'provider' => $this->name(),
                'model' => $requestedModel,
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Groq unreachable', ['error' => $e->getMessage()]);
            throw new RuntimeException('GROQ_UNREACHABLE');
        }
    }

    public function health(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])
            ->timeout(10)
            ->connectTimeout(3)
            ->get("{$this->baseUrl}/models");

            if ($response->successful()) {
                $models = collect($response->json()['data'] ?? []);
                $primaryAvailable = $models->contains('id', config('intelligence.groq.primary_model'));
                $secondaryAvailable = $models->contains('id', config('intelligence.groq.secondary_model'));

                return [
                    'status' => 'healthy',
                    'primary_model_available' => $primaryAvailable,
                    'secondary_model_available' => $secondaryAvailable,
                    'total_models' => $models->count(),
                ];
            }

            return ['status' => 'unhealthy', 'details' => ['status_code' => $response->status()]];
        } catch (\Exception $e) {
            return ['status' => 'unreachable', 'details' => ['error' => $e->getMessage()]];
        }
    }

    public function name(): string
    {
        return 'groq';
    }

    public function model(): string
    {
        return $this->model;
    }
}
