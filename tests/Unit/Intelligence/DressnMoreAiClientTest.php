<?php

namespace Tests\Unit\Intelligence;

use App\Services\Intelligence\DressnMoreAiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class DressnMoreAiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('intelligence.service.base_url', 'http://127.0.0.1:11500');
        config()->set('intelligence.service.auth_key', 'test-key-123');
        config()->set('intelligence.service.timeout', 120);
        config()->set('intelligence.service.connect_timeout', 5);
        config()->set('intelligence.generation.default_output_tokens', 96);
        config()->set('intelligence.generation.max_output_tokens', 160);
        config()->set('intelligence.generation.temperature', 0.7);
    }

    public function test_sends_auth_header(): void
    {
        Http::fake([
            'http://127.0.0.1:11500/v1/generate' => Http::response([
                'response' => 'Hi',
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15],
                'latency_ms' => 1000,
            ]),
        ]);

        $client = new DressnMoreAiClient();
        $client->generate([['role' => 'user', 'content' => 'Hello']]);

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('X-DressnMore-AI-Key', 'test-key-123');
        });
    }

    public function test_uses_correct_base_url(): void
    {
        Http::fake([
            'http://127.0.0.1:11500/v1/generate' => Http::response([
                'response' => 'Hi',
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2],
                'latency_ms' => 100,
            ]),
        ]);

        $client = new DressnMoreAiClient();
        $client->generate([['role' => 'user', 'content' => 'Test']]);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'http://127.0.0.1:11500/v1/generate');
        });
    }

    public function test_default_output_tokens_is_96(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => 'Hi',
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2],
                'latency_ms' => 100,
            ]),
        ]);

        $client = new DressnMoreAiClient();
        $client->generate([['role' => 'user', 'content' => 'Test']]);

        Http::assertSent(function (Request $request) {
            return $request['max_tokens'] === 96;
        });
    }

    public function test_max_output_tokens_ceiling_at_160(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => 'Hi',
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2],
                'latency_ms' => 100,
            ]),
        ]);

        $client = new DressnMoreAiClient();
        $client->generate([['role' => 'user', 'content' => 'Test']], ['max_tokens' => 200]);

        Http::assertSent(function (Request $request) {
            return $request['max_tokens'] === 160;
        });
    }

    public function test_maps_input_output_total_tokens_correctly(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => 'Hello there',
                'usage' => ['input_tokens' => 31, 'output_tokens' => 9, 'total_tokens' => 40],
                'latency_ms' => 5000,
            ]),
        ]);

        $client = new DressnMoreAiClient();
        $result = $client->generate([['role' => 'user', 'content' => 'Hi']]);

        $this->assertEquals(31, $result['input_tokens']);
        $this->assertEquals(9, $result['output_tokens']);
        $this->assertEquals(40, $result['total_tokens']);
        $this->assertEquals(5000, $result['generation_time_ms']);
    }

    public function test_401_throws_runtime_exception(): void
    {
        Http::fake(['*' => Http::response('', 401)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('authentication failed');

        $client = new DressnMoreAiClient();
        $client->generate([['role' => 'user', 'content' => 'Test']]);
    }

    public function test_429_throws_runtime_exception(): void
    {
        Http::fake(['*' => Http::response('', 429)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('busy');

        $client = new DressnMoreAiClient();
        $client->generate([['role' => 'user', 'content' => 'Test']]);
    }

    public function test_503_timeout_throws_runtime_exception(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $this->expectException(RuntimeException::class);

        $client = new DressnMoreAiClient();
        $client->generate([['role' => 'user', 'content' => 'Test']]);
    }

    public function test_secret_is_not_included_in_error_message(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        try {
            $client = new DressnMoreAiClient();
            $client->generate([['role' => 'user', 'content' => 'Test']]);
            $this->fail('Expected exception');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('test-key-123', $e->getMessage());
        }
    }

    public function test_health_returns_status(): void
    {
        Http::fake([
            'http://127.0.0.1:11500/health' => Http::response(['model_name' => 'qwen'], 200),
        ]);

        $client = new DressnMoreAiClient();
        $result = $client->health();

        $this->assertEquals('healthy', $result['status']);
    }

    public function test_health_unreachable_returns_unreachable(): void
    {
        Http::fake(function () {
            throw new \Exception('Connection refused');
        });

        $client = new DressnMoreAiClient();
        $result = $client->health();

        $this->assertEquals('unreachable', $result['status']);
    }
}
