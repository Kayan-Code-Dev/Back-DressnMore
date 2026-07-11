<?php

namespace App\Services\Intelligence;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DressnMoreAiClient
{
    private string $baseUrl;
    private string $authKey;
    private int $timeout;
    private int $connectTimeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('intelligence.service.base_url'), '/');
        $this->authKey = config('intelligence.service.auth_key');
        $this->timeout = config('intelligence.service.timeout', 120);
        $this->connectTimeout = config('intelligence.service.connect_timeout', 5);

        if (empty($this->authKey)) {
            throw new RuntimeException('AI service auth key is not configured.');
        }
    }

    public function generate(array $messages, array $options = []): array
    {
        $defaultTokens = config('intelligence.generation.default_output_tokens', 96);
        $maxTokens = config('intelligence.generation.max_output_tokens', 160);

        $requestedTokens = $options['max_tokens'] ?? $defaultTokens;
        $requestedTokens = min($requestedTokens, $maxTokens);

        $payload = [
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? config('intelligence.generation.temperature', 0.7),
            'max_tokens' => $requestedTokens,
            'stream' => false,
        ];

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-DressnMore-AI-Key' => $this->authKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->post("{$this->baseUrl}/v1/generate", $payload);

            if ($response->status() === 429) {
                Log::warning('AI service rate limited');
                throw new RuntimeException('AI service is busy. Please try again in a moment.');
            }

            if ($response->status() === 413) {
                Log::warning('AI service request too large');
                throw new RuntimeException('Request too large. Please shorten your message.');
            }

            if ($response->status() === 401 || $response->status() === 403) {
                Log::error('AI service auth rejected');
                throw new RuntimeException('AI service authentication failed.');
            }

            if ($response->failed()) {
                Log::error('AI service error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new RuntimeException('AI service returned an error. Please try again.');
            }

            $data = $response->json();

            // Map token fields from inference service response
            $inputTokens = $data['usage']['input_tokens'] ?? null;
            $outputTokens = $data['usage']['output_tokens'] ?? null;
            $totalTokens = $data['usage']['total_tokens'] ?? null;
            $latencyMs = $data['latency_ms'] ?? null;

            return [
                'response' => $data['response'] ?? '',
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
                'generation_time_ms' => $latencyMs,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('AI service unreachable', ['error' => $e->getMessage()]);
            throw new RuntimeException('AI assistant is currently unavailable. Please try again later.');
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('AI service request failed', ['error' => $e->getMessage()]);
            throw new RuntimeException('Failed to communicate with AI assistant. Please try again.');
        }
    }

    public function health(): array
    {
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-DressnMore-AI-Key' => $this->authKey,
            ])
            ->timeout(10)
            ->connectTimeout(3)
            ->get("{$this->baseUrl}/health");

            if ($response->successful()) {
                return ['status' => 'healthy', 'details' => $response->json()];
            }

            return ['status' => 'unhealthy', 'details' => ['status_code' => $response->status()]];
        } catch (\Exception $e) {
            return ['status' => 'unreachable', 'details' => ['error' => $e->getMessage()]];
        }
    }
}
