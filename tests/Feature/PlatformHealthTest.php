<?php

namespace Tests\Feature;

use Tests\TestCase;

class PlatformHealthTest extends TestCase
{
    public function test_platform_health_endpoint_returns_json(): void
    {
        $response = $this->getJson('/api/platform/health');

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'app_name',
                    'environment',
                    'central_database_connection',
                    'timestamp',
                ],
            ]);
    }
}
