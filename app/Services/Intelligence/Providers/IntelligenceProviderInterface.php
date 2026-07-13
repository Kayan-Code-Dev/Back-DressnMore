<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Providers;

/**
 * Contract for all Intelligence providers (Groq, Local, etc.)
 */
interface IntelligenceProviderInterface
{
    /**
     * Send a chat request. Returns array with response, usage, and metadata.
     *
     * @param array $messages OpenAI-format messages
     * @param array $tools    JSON Schema tool definitions (optional)
     * @param array $options  temperature, max_tokens, etc.
     * @return array {
     *   response: string,
     *   tool_calls: array,
     *   input_tokens: int,
     *   output_tokens: int,
     *   total_tokens: int,
     *   generation_time_ms: int,
     *   provider: string,
     *   model: string,
     * }
     */
    public function chat(array $messages, array $tools = [], array $options = []): array;

    /**
     * Check if the provider and model are available.
     */
    public function health(): array;

    /**
     * Provider name for logging and metadata.
     */
    public function name(): string;

    /**
     * Current model being used.
     */
    public function model(): string;
}
