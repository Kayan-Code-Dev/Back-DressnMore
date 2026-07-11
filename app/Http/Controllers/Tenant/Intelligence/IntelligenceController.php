<?php

namespace App\Http\Controllers\Tenant\Intelligence;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Intelligence\StoreConversationRequest;
use App\Http\Requests\Tenant\Intelligence\StoreMessageRequest;
use App\Http\Resources\Tenant\Intelligence\ConversationResource;
use App\Http\Resources\Tenant\Intelligence\MessageResource;
use App\Http\Resources\Tenant\Intelligence\RunResource;
use App\Jobs\Intelligence\ProcessAiChatRun;
use App\Models\Tenant\Intelligence\AiConversation;
use App\Models\Tenant\Intelligence\AiMessage;
use App\Models\Tenant\Intelligence\AiRun;
use App\Services\Intelligence\DressnMoreAiClient;
use App\Services\Intelligence\QueueProtection;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class IntelligenceController extends Controller
{
    public function __construct(
        private readonly QueueProtection $queueProtection,
        private readonly DressnMoreAiClient $aiClient,
    ) {}

    /**
     * List conversations for the authenticated user.
     */
    public function conversations(Request $request): JsonResponse
    {
        $perPage = max(1, min(50, $request->integer('per_page', 15)));

        $conversations = AiConversation::forUser($request->user()->id)
            ->active()
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $conversations,
            ConversationResource::collection($conversations->items())->resolve(),
        );
    }

    /**
     * Create a new conversation.
     */
    public function storeConversation(StoreConversationRequest $request): JsonResponse
    {
        $user = $request->user();

        $maxConversations = config('intelligence.limits.max_conversations_per_user', 10);
        $currentCount = AiConversation::forUser($user->id)->active()->count();

        if ($currentCount >= $maxConversations) {
            // Archive oldest conversation
            AiConversation::forUser($user->id)
                ->active()
                ->orderBy('last_message_at')
                ->first()
                ?->update(['status' => 'archived']);
        }

        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'title' => $request->input('title') ?? 'New Chat',
            'status' => 'active',
        ]);

        return ApiResponse::success(
            new ConversationResource($conversation),
            'Conversation created',
            201,
        );
    }

    /**
     * Show a single conversation with messages.
     */
    public function showConversation(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->load([
            'messages' => fn ($q) => $q->orderBy('id')->limit(100),
        ]);

        return ApiResponse::success([
            'conversation' => new ConversationResource($conversation),
            'messages' => MessageResource::collection($conversation->messages)->resolve(),
        ]);
    }

    /**
     * Archive a conversation.
     */
    public function archiveConversation(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->update(['status' => 'archived']);

        return ApiResponse::success(null, 'Conversation archived');
    }

    /**
     * Send a message and queue AI processing.
     */
    public function storeMessage(StoreMessageRequest $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $user = $request->user();
        $requestId = $request->input('request_id');

        // Idempotency: check for existing message with same request_id
        if ($requestId !== null) {
            $existingMessage = $conversation->messages()
                ->where('request_id', $requestId)
                ->first();

            if ($existingMessage !== null) {
                $existingRun = AiRun::where('message_id', $existingMessage->id)->first();
                return ApiResponse::success([
                    'message' => new MessageResource($existingMessage),
                    'run' => $existingRun ? new RunResource($existingRun) : null,
                ], 'Message already processed', 200);
            }
        }

        // Check queue protection
        try {
            $this->queueProtection->canStartRun($user->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 429);
        }

        // Check max messages
        $maxMessages = config('intelligence.limits.max_messages_per_conversation', 100);
        if ($conversation->messages()->count() >= $maxMessages) {
            return ApiResponse::error('Conversation message limit reached. Please start a new conversation.', 422);
        }

        // Save user message
        $messageData = [
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $request->input('content'),
        ];
        if ($requestId !== null) {
            $messageData['request_id'] = $requestId;
        }
        $message = $conversation->messages()->create($messageData);

        // Update conversation timestamp
        $conversation->update(['last_message_at' => now()]);

        // Generate title from first message if not set
        if (empty($conversation->title) || $conversation->title === 'New Chat') {
            $title = mb_substr($request->input('content'), 0, 50);
            $conversation->update(['title' => $title]);
        }

        // Create run record
        $run = AiRun::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'message_id' => $message->id,
            'status' => 'pending',
        ]);

        // Dispatch to queue
        ProcessAiChatRun::dispatch($run->id);

        return ApiResponse::success([
            'message' => new MessageResource($message),
            'run' => new RunResource($run),
        ], 'Message accepted for processing', 202);
    }

    /**
     * Poll for run status.
     */
    public function showRun(Request $request, AiRun $run): JsonResponse
    {
        if ($run->user_id !== $request->user()->id) {
            return ApiResponse::forbidden();
        }

        $run->load('assistantMessage');

        return ApiResponse::success(new RunResource($run));
    }

    /**
     * Get AI service health status.
     */
    public function health(): JsonResponse
    {
        $health = $this->aiClient->health();

        return ApiResponse::success([
            'ai_service' => $health,
            'queue_status' => $this->queueProtection->getUserQueueStatus(request()->user()->id),
        ]);
    }

    /**
     * Ensure the user owns the conversation.
     */
    private function authorizeConversation(Request $request, AiConversation $conversation): void
    {
        if ($conversation->user_id !== $request->user()->id) {
            abort(403, 'This conversation does not belong to you.');
        }
    }
}