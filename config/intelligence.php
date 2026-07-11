<?php

return [
    'service' => [
        'base_url' => env('AI_SERVICE_URL', 'http://127.0.0.1:11500'),
        'auth_key' => env('AI_SERVICE_KEY', ''),
        'timeout' => (int) env('AI_SERVICE_TIMEOUT', 120),
        'connect_timeout' => (int) env('AI_SERVICE_CONNECT_TIMEOUT', 5),
    ],
    'limits' => [
        'max_input_chars' => (int) env('AI_MAX_INPUT_CHARS', 1500),
        'max_history_messages' => (int) env('AI_MAX_HISTORY_MESSAGES', 20),
        'max_conversations_per_user' => (int) env('AI_MAX_CONVERSATIONS_PER_USER', 10),
        'max_messages_per_conversation' => (int) env('AI_MAX_MESSAGES_PER_CONVERSATION', 100),
    ],
    'generation' => [
        'default_output_tokens' => (int) env('AI_DEFAULT_OUTPUT_TOKENS', 96),
        'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 160),
        'temperature' => (float) env('AI_TEMPERATURE', 0.7),
    ],
    'queue' => [
        'queue_name' => env('AI_QUEUE_NAME', 'intelligence'),
        'max_active_runs_per_user' => (int) env('AI_MAX_ACTIVE_RUNS_PER_USER', 1),
        'max_pending_runs_per_tenant' => (int) env('AI_MAX_PENDING_RUNS_PER_TENANT', 5),
        'retry_after' => (int) env('AI_RETRY_AFTER', 90),
    ],
    'features' => [
        'plan_feature_key' => 'ai_assistant.enabled',
    ],
    'permissions' => [
        'view' => 'intelligence.view',
        'chat' => 'intelligence.chat',
    ],
];
