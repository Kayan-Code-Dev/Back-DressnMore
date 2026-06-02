<?php

namespace Tests\Feature;

use Tests\TestCase;

class TenantHealthTest extends TestCase
{
    public function test_tenant_health_requires_workspace(): void
    {
        $response = $this->getJson('/api/tenant/health');

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJson([
                'message' => 'Tenant context is required',
            ]);
    }
}
