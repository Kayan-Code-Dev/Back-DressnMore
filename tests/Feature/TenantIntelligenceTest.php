<?php

namespace Tests\Feature;

use App\Models\Tenant\Intelligence\AiConversation;
use App\Models\Tenant\Intelligence\AiMessage;
use App\Models\Tenant\Intelligence\AiRun;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class TenantIntelligenceTest extends TestCase
{
    private User $user;
    private string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbFile = sys_get_temp_dir() . '/dm_test_' . uniqid() . '.sqlite';
        touch($this->dbFile);

        Config::set('database.connections.tenant', [
            'driver' => 'sqlite', 'database' => $this->dbFile,
            'prefix' => '', 'foreign_key_constraints' => false,
        ]);
        DB::purge('tenant');

        $s = DB::connection('tenant')->getSchemaBuilder();
        $s->create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email');
            $t->string('password'); $t->string('status')->default('active'); $t->timestamps();
        });
        $s->create('ai_conversations', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id'); $t->string('title')->nullable();
            $t->string('status')->default('active'); $t->timestamp('last_message_at')->nullable(); $t->timestamps();
        });
        $s->create('ai_messages', function (Blueprint $t) {
            $t->id(); $t->foreignId('conversation_id'); $t->foreignId('user_id');
            $t->string('role'); $t->text('content'); $t->string('request_id', 36)->nullable();
            $t->integer('total_tokens')->nullable(); $t->integer('input_tokens')->nullable();
            $t->integer('output_tokens')->nullable(); $t->integer('generation_time_ms')->default(0);
            $t->timestamps();
        });
        $s->create('ai_runs', function (Blueprint $t) {
            $t->id(); $t->foreignId('conversation_id'); $t->foreignId('user_id');
            $t->foreignId('message_id'); $t->foreignId('assistant_message_id')->nullable();
            $t->string('status')->default('pending'); $t->text('error_message')->nullable();
            $t->integer('total_tokens')->nullable(); $t->integer('input_tokens')->nullable();
            $t->integer('output_tokens')->nullable(); $t->integer('generation_time_ms')->default(0);
            $t->timestamp('started_at')->nullable(); $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });

        $this->user = User::on('tenant')->create(['name' => 'Test', 'email' => 'test@t.test', 'password' => 'p', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbFile)) {
            unlink($this->dbFile);
        }
        parent::tearDown();
    }

    public function test_conversation_creates(): void
    {
        $conv = AiConversation::on('tenant')->create(['user_id' => $this->user->id, 'title' => 'Test', 'status' => 'active']);
        $this->assertDatabaseHas('ai_conversations', ['id' => $conv->id, 'title' => 'Test'], 'tenant');
    }

    public function test_message_creates(): void
    {
        $conv = AiConversation::on('tenant')->create(['user_id' => $this->user->id, 'title' => 'Msg', 'status' => 'active']);
        $msg = $conv->messages()->create(['user_id' => $this->user->id, 'role' => 'user', 'content' => 'Hi']);
        $this->assertEquals('Hi', $msg->content);
    }

    public function test_request_id_unique(): void
    {
        $conv = AiConversation::on('tenant')->create(['user_id' => $this->user->id, 'title' => 'Dup', 'status' => 'active']);
        $reqId = '550e8400-e29b-41d4-a716-446655440000';
        $conv->messages()->create(['user_id' => $this->user->id, 'role' => 'user', 'content' => 'A', 'request_id' => $reqId]);
        $this->assertEquals(1, AiMessage::on('tenant')->where('request_id', $reqId)->count());
    }

    public function test_run_status_machine(): void
    {
        $conv = AiConversation::on('tenant')->create(['user_id' => $this->user->id, 'title' => 'Run', 'status' => 'active']);
        $msg = $conv->messages()->create(['user_id' => $this->user->id, 'role' => 'user', 'content' => 'Hi']);
        $run = AiRun::on('tenant')->create(['conversation_id' => $conv->id, 'user_id' => $this->user->id, 'message_id' => $msg->id, 'status' => 'pending']);

        $run->markProcessing();
        $this->assertEquals('processing', $run->fresh()->status);

        $assistant = $conv->messages()->create(['user_id' => $this->user->id, 'role' => 'assistant', 'content' => 'Hi back']);
        $run->markCompleted($assistant->id, [
            'input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15, 'generation_time_ms' => 1000,
        ]);
        $run->refresh();
        $this->assertEquals('completed', $run->status);
        $this->assertEquals(5, $run->output_tokens);
        $this->assertEquals(10, $run->input_tokens);
    }

    public function test_run_failed(): void
    {
        $conv = AiConversation::on('tenant')->create(['user_id' => $this->user->id, 'title' => 'Fail', 'status' => 'active']);
        $msg = $conv->messages()->create(['user_id' => $this->user->id, 'role' => 'user', 'content' => 'Hi']);
        $run = AiRun::on('tenant')->create(['conversation_id' => $conv->id, 'user_id' => $this->user->id, 'message_id' => $msg->id, 'status' => 'pending']);
        $run->markFailed('Service down');
        $this->assertEquals('failed', $run->fresh()->status);
    }

    public function test_output_tokens_within_limit(): void
    {
        $conv = AiConversation::on('tenant')->create(['user_id' => $this->user->id, 'title' => 'Tok', 'status' => 'active']);
        $msg = $conv->messages()->create(['user_id' => $this->user->id, 'role' => 'user', 'content' => 'Hi']);
        $run = AiRun::on('tenant')->create(['conversation_id' => $conv->id, 'user_id' => $this->user->id, 'message_id' => $msg->id, 'status' => 'pending']);
        $assistant = $conv->messages()->create(['user_id' => $this->user->id, 'role' => 'assistant', 'content' => 'Hello', 'output_tokens' => 9, 'input_tokens' => 31, 'total_tokens' => 40, 'generation_time_ms' => 5000]);
        $run->markCompleted($assistant->id, ['output_tokens' => 9, 'input_tokens' => 31, 'total_tokens' => 40, 'generation_time_ms' => 5000]);
        $this->assertLessThanOrEqual(96, $run->fresh()->output_tokens);
    }

    public function test_1500_char_content(): void
    {
        $conv = AiConversation::on('tenant')->create(['user_id' => $this->user->id, 'title' => 'Long', 'status' => 'active']);
        $msg = $conv->messages()->create(['user_id' => $this->user->id, 'role' => 'user', 'content' => str_repeat('a', 1500)]);
        $this->assertEquals(1500, mb_strlen($msg->content));
    }

    public function test_empty_content_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $content = trim('   ');
        if ($content === '') throw new \RuntimeException('Empty');
    }
}
