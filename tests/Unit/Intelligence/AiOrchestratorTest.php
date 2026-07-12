<?php

namespace Tests\Unit\Intelligence;

use App\Models\Central\Tenant;
use App\Models\Tenant\Intelligence\AiConversation;
use App\Models\Tenant\Intelligence\AiMessage;
use App\Models\Tenant\Intelligence\AiRun;
use App\Models\Tenant\User;
use App\Services\Intelligence\AiOrchestrator;
use App\Services\Intelligence\Tools\BusinessToolExecutor;
use App\Services\Intelligence\DressnMoreAiClient;
use App\Services\Tenant\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class AiOrchestratorTest extends TestCase
{
    private string $dbFile;
    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('intelligence.generation.default_output_tokens', 96);
        config()->set('intelligence.generation.max_output_tokens', 160);
        config()->set('intelligence.generation.temperature', 0.7);
        config()->set('intelligence.limits.max_history_messages', 20);
        config()->set('intelligence.limits.max_input_chars', 1500);

        $this->dbFile = sys_get_temp_dir() . '/dm_orch_' . uniqid() . '.sqlite';
        touch($this->dbFile);
        Config::set('database.connections.orch_test', [
            'driver' => 'sqlite', 'database' => $this->dbFile,
            'prefix' => '', 'foreign_key_constraints' => false,
        ]);
        DB::purge('orch_test');

        $s = DB::connection('orch_test')->getSchemaBuilder();
        $s->create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('email'); $t->string('password'); $t->string('status')->default('active'); $t->timestamps(); });
        $s->create('ai_conversations', function (Blueprint $t) { $t->id(); $t->foreignId('user_id'); $t->string('title')->nullable(); $t->string('status')->default('active'); $t->timestamp('last_message_at')->nullable(); $t->timestamps(); });
        $s->create('ai_messages', function (Blueprint $t) { $t->id(); $t->foreignId('conversation_id'); $t->foreignId('user_id'); $t->string('role'); $t->text('content'); $t->string('request_id', 36)->nullable(); $t->integer('total_tokens')->nullable(); $t->integer('input_tokens')->nullable(); $t->integer('output_tokens')->nullable(); $t->integer('generation_time_ms')->default(0); $t->timestamps(); });
        $s->create('ai_runs', function (Blueprint $t) { $t->id(); $t->foreignId('conversation_id'); $t->foreignId('user_id'); $t->foreignId('message_id'); $t->foreignId('assistant_message_id')->nullable(); $t->string('status')->default('pending'); $t->text('error_message')->nullable(); $t->integer('total_tokens')->nullable(); $t->integer('input_tokens')->nullable(); $t->integer('output_tokens')->nullable(); $t->integer('generation_time_ms')->default(0); $t->timestamp('started_at')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamps(); });

        $this->tenant = new Tenant(['name' => 'TestTenant']);
        $this->tenant->id = 1;
        $this->user = User::on('orch_test')->create(['name' => 'Test', 'email' => 'test@test.com', 'password' => 'p', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbFile)) unlink($this->dbFile);
        Mockery::close();
        parent::tearDown();
    }

    private function makeOrchestrator(array $clientResult): AiOrchestrator
    {
        $client = Mockery::mock(DressnMoreAiClient::class);
        $client->shouldReceive('generate')->once()->andReturn($clientResult);

        $tenantContext = Mockery::mock(TenantContext::class);
        $tenantContext->shouldReceive('tenant')->andReturn($this->tenant);
        $tenantContext->shouldReceive('slug')->andReturn('test');

        $toolExecutor = Mockery::mock(BusinessToolExecutor::class);
        $toolExecutor->shouldReceive('tryAnswer')->andReturn([
            'handled' => true,
            'response' => 'Test tool response',
            'facts' => [],
            'tools_executed' => [],
            'execution_ms' => 100,
            'model_needed' => false,
        ]);
        $toolExecutor->shouldIgnoreMissing();
        return new AiOrchestrator($client, $tenantContext, $toolExecutor);
    }

    public function test_default_output_tokens_are_96(): void
    {
        $orch = $this->makeOrchestrator([
            'response' => 'Hello', 'input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15, 'generation_time_ms' => 1000,
        ]);

        $conv = AiConversation::on('orch_test')->create(['user_id' => $this->user->id, 'title' => 'Test', 'status' => 'active']);
        $msg = $conv->messages()->create(['user_id' => $this->user->id, 'role' => 'user', 'content' => 'Hi']);
        $run = AiRun::on('orch_test')->create(['conversation_id' => $conv->id, 'user_id' => $this->user->id, 'message_id' => $msg->id, 'status' => 'pending']);

        $orch->executeRun($run);

        $run->refresh();
        $this->assertEquals('completed', $run->status);
        $this->assertEquals(5, $run->output_tokens);
    }

    public function test_system_prompt_contains_live_data_boundary(): void
    {
        $capturedMessages = null;
        $client = Mockery::mock(DressnMoreAiClient::class);
        $client->shouldReceive('generate')->once()->andReturnUsing(function ($messages) use (&$capturedMessages) {
            $capturedMessages = $messages;
            return ['response' => 'Hello', 'input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15, 'generation_time_ms' => 1000];
        });

        $tenantContext = Mockery::mock(TenantContext::class);
        $tenantContext->shouldReceive('tenant')->andReturn($this->tenant);
        $tenantContext->shouldReceive('slug')->andReturn('test');
        $toolExecutor = Mockery::mock(BusinessToolExecutor::class);
        $toolExecutor->shouldReceive('tryAnswer')->andReturn([
            'handled' => true,
            'response' => 'Test tool response',
            'facts' => [],
            'tools_executed' => [],
            'execution_ms' => 100,
            'model_needed' => false,
        ]);
        $toolExecutor->shouldIgnoreMissing();
        $orch = new AiOrchestrator($client, $tenantContext, $toolExecutor);

        $conv = AiConversation::on('orch_test')->create(['user_id' => $this->user->id, 'title' => 'Test', 'status' => 'active']);
        $msg = $conv->messages()->create(['user_id' => $this->user->id, 'role' => 'user', 'content' => 'What is revenue?']);
        $run = AiRun::on('orch_test')->create(['conversation_id' => $conv->id, 'user_id' => $this->user->id, 'message_id' => $msg->id, 'status' => 'pending']);

        $orch->executeRun($run);

        $systemMsg = collect($capturedMessages)->firstWhere('role', 'system');
        $this->assertStringContainsString('You do NOT have database access', $systemMsg['content']);
        $this->assertStringContainsString('Do NOT fabricate numbers', $systemMsg['content']);
    }

    public function test_user_content_is_separate_user_role(): void
    {
        $capturedMessages = null;
        $client = Mockery::mock(DressnMoreAiClient::class);
        $client->shouldReceive('generate')->once()->andReturnUsing(function ($messages) use (&$capturedMessages) {
            $capturedMessages = $messages;
            return ['response' => 'Hi', 'input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15, 'generation_time_ms' => 1000];
        });

        $tenantContext = Mockery::mock(TenantContext::class);
        $tenantContext->shouldReceive('tenant')->andReturn($this->tenant);
        $tenantContext->shouldReceive('slug')->andReturn('test');
        $toolExecutor = Mockery::mock(BusinessToolExecutor::class);
        $toolExecutor->shouldReceive('tryAnswer')->andReturn([
            'handled' => true,
            'response' => 'Test tool response',
            'facts' => [],
            'tools_executed' => [],
            'execution_ms' => 100,
            'model_needed' => false,
        ]);
        $toolExecutor->shouldIgnoreMissing();
        $orch = new AiOrchestrator($client, $tenantContext, $toolExecutor);

        $conv = AiConversation::on('orch_test')->create(['user_id' => $this->user->id, 'title' => 'Test', 'status' => 'active']);
        $msg = $conv->messages()->create(['user_id' => $this->user->id, 'role' => 'user', 'content' => 'Hello test']);
        $run = AiRun::on('orch_test')->create(['conversation_id' => $conv->id, 'user_id' => $this->user->id, 'message_id' => $msg->id, 'status' => 'pending']);

        $orch->executeRun($run);

        $userMsgs = collect($capturedMessages)->where('role', 'user');
        $this->assertCount(1, $userMsgs);
        $this->assertEquals('Hello test', $userMsgs->first()['content']);
    }

    public function test_max_output_tokens_cannot_exceed_160(): void
    {
        $requestedTokens = null;
        $client = Mockery::mock(DressnMoreAiClient::class);
        $client->shouldReceive('generate')->once()->andReturnUsing(function ($messages, $options) use (&$requestedTokens) {
            $requestedTokens = $options['max_tokens'];
            return ['response' => 'Hi', 'input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15, 'generation_time_ms' => 1000];
        });

        $tenantContext = Mockery::mock(TenantContext::class);
        $tenantContext->shouldReceive('tenant')->andReturn($this->tenant);
        $tenantContext->shouldReceive('slug')->andReturn('test');
        $toolExecutor = Mockery::mock(BusinessToolExecutor::class);
        $toolExecutor->shouldReceive('tryAnswer')->andReturn([
            'handled' => true,
            'response' => 'Test tool response',
            'facts' => [],
            'tools_executed' => [],
            'execution_ms' => 100,
            'model_needed' => false,
        ]);
        $toolExecutor->shouldIgnoreMissing();
        $orch = new AiOrchestrator($client, $tenantContext, $toolExecutor);

        $conv = AiConversation::on('orch_test')->create(['user_id' => $this->user->id, 'title' => 'Test', 'status' => 'active']);
        $msg = $conv->messages()->create(['user_id' => $this->user->id, 'role' => 'user', 'content' => 'Hi']);
        $run = AiRun::on('orch_test')->create(['conversation_id' => $conv->id, 'user_id' => $this->user->id, 'message_id' => $msg->id, 'status' => 'pending']);

        $orch->executeRun($run);

        $this->assertEquals(96, $requestedTokens);
        $this->assertLessThanOrEqual(160, $requestedTokens);
    }

    public function test_token_fields_stored_separately(): void
    {
        $orch = $this->makeOrchestrator([
            'response' => 'Hello',
            'input_tokens' => 31, 'output_tokens' => 9, 'total_tokens' => 40, 'generation_time_ms' => 5000,
        ]);

        $conv = AiConversation::on('orch_test')->create(['user_id' => $this->user->id, 'title' => 'Tokens', 'status' => 'active']);
        $msg = $conv->messages()->create(['user_id' => $this->user->id, 'role' => 'user', 'content' => 'Hi']);
        $run = AiRun::on('orch_test')->create(['conversation_id' => $conv->id, 'user_id' => $this->user->id, 'message_id' => $msg->id, 'status' => 'pending']);

        $orch->executeRun($run);

        $run->refresh();
        $this->assertEquals(31, $run->input_tokens);
        $this->assertEquals(9, $run->output_tokens);
        $this->assertEquals(40, $run->total_tokens);
        $this->assertEquals(5000, $run->generation_time_ms);
        $this->assertLessThanOrEqual(96, $run->output_tokens);
    }
}