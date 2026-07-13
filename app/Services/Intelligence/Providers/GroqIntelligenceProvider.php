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
    private int $maxRetries;

    public function __construct(string $model = null)
    {
        $this->baseUrl = rtrim(config('intelligence.groq.base_url', 'https://api.groq.com/openai/v1'), '/');
        $this->apiKey = config('intelligence.groq.api_key', '');
        $this->model = $model ?? config('intelligence.groq.primary_model', 'qwen/qwen3-32b');
        $this->timeout = config('intelligence.groq.timeout', 25);
        $this->connectTimeout = config('intelligence.groq.connect_timeout', 5);
        $this->maxOutputTokens = config('intelligence.groq.max_output_tokens', 800);
        $this->maxRetries = 3;

        if (empty($this->apiKey)) {
            throw new RuntimeException('GROQ_AUTH_FAILED');
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

        $lastError = null;
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->post("{$this->baseUrl}/chat/completions", $payload);

                // Success
                if ($response->successful()) {
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
                }

                // 429 Rate limited — retry with backoff
                if ($response->status() === 429) {
                    $lastError = 'GROQ_RATE_LIMITED';
                    $retryAfter = (int) $response->header('Retry-After', 2 ** $attempt);
                    $waitMs = min($retryAfter * 1000, 8000);
                    Log::info("Groq rate limited, retrying", ['attempt' => $attempt, 'wait_ms' => $waitMs]);
                    if ($attempt < $this->maxRetries) {
                        usleep($waitMs * 1000);
                        continue;
                    }
                }

                // 401/403 Auth error — don't retry
                if (in_array($response->status(), [401, 403])) {
                    throw new RuntimeException('GROQ_AUTH_FAILED');
                }

                // Other errors — retry once
                $lastError = 'GROQ_API_ERROR';
                Log::warning('Groq API error', [
                    'status' => $response->status(),
                    'attempt' => $attempt,
                    'body' => substr($response->body(), 0, 200),
                ]);
                if ($attempt < $this->maxRetries) {
                    usleep(1000 * 1000);
                    continue;
                }

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastError = 'GROQ_UNREACHABLE';
                Log::warning('Groq connection failed', ['attempt' => $attempt, 'error' => $e->getMessage()]);
                if ($attempt < $this->maxRetries) {
                    usleep(2000 * 1000);
                    continue;
                }
            }
        }

        // All retries exhausted
        throw new RuntimeException($lastError ?? 'GROQ_FAILED');
    }

    public function health(): array
    {
        try {
            $response = Http::withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
                ->timeout(10)->connectTimeout(3)
                ->get("{$this->baseUrl}/models");

            if ($response->successful()) {
                $models = collect($response->json()['data'] ?? []);
                return [
                    'status' => 'healthy',
                    'primary_available' => $models->contains('id', config('intelligence.groq.primary_model')),
                    'secondary_available' => $models->contains('id', config('intelligence.groq.secondary_model')),
                ];
            }
            return ['status' => 'unhealthy'];
        } catch (\Exception $e) {
            return ['status' => 'unreachable'];
        }
    }

    public function name(): string { return 'groq'; }
    public function model(): string { return $this->model; }
}
