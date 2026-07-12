<?php

namespace Tests\Unit\Intelligence\Tools;

use App\Services\Intelligence\Tools\BusinessToolContext;
use App\Services\Intelligence\Tools\BusinessToolRegistry;
use App\Services\Intelligence\Tools\BusinessToolResult;
use App\Services\Intelligence\Tools\Contracts\SafeBusinessTool;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class BusinessToolRegistryTest extends TestCase
{
    public function test_registry_starts_empty(): void
    {
        $registry = new BusinessToolRegistry();
        $this->assertSame([], $registry->names());
        $this->assertNull($registry->get('anything'));
    }

    public function test_registers_all_standard_tools(): void
    {
        $registry = BusinessToolRegistry::withStandardTools();
        $names = $registry->names();
        $this->assertContains('revenue_summary', $names);
        $this->assertContains('active_reservations', $names);
        $this->assertContains('late_returns', $names);
        $this->assertContains('pending_deliveries', $names);
        $this->assertContains('active_customers', $names);
        $this->assertContains('inactive_dresses', $names);
        $this->assertContains('business_snapshot', $names);
        $this->assertContains('business_health', $names);
        $this->assertContains('daily_brief', $names);
        $this->assertCount(9, $names);
    }

    public function test_retrieves_tool_by_name(): void
    {
        $registry = BusinessToolRegistry::withStandardTools();
        $tool = $registry->get('revenue_summary');
        $this->assertInstanceOf(SafeBusinessTool::class, $tool);
        $this->assertSame('revenue_summary', $tool->name());
        $this->assertSame('1.0.0', $tool->version());
    }

    public function test_returns_null_for_unregistered(): void
    {
        $registry = new BusinessToolRegistry();
        $this->assertNull($registry->get('nonexistent'));
    }

    public function test_whitelist_enforcement(): void
    {
        $registry = BusinessToolRegistry::withStandardTools();
        $this->assertTrue($registry->has('revenue_summary'));
        $this->assertFalse($registry->has('arbitrary_tool'));
    }

    public function test_metadata(): void
    {
        $registry = BusinessToolRegistry::withStandardTools();
        $meta = $registry->metadata();
        $this->assertCount(9, $meta);
        $revenueMeta = collect($meta)->first(fn ($m) => $m['name'] === 'revenue_summary');
        $this->assertNotNull($revenueMeta);
        $this->assertNotEmpty($revenueMeta['description']);
        $this->assertContains('invoices.view', $revenueMeta['permissions']);
    }

    public function test_execution_denied_without_permissions(): void
    {
        $registry = BusinessToolRegistry::withStandardTools();
        $user = Mockery::mock(\App\Models\Tenant\User::class);
        $user->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user->shouldReceive('getAttribute')->with('branch_id')->andReturn(1);
        $user->shouldReceive('getAttribute')->with('roles')->andReturn(new Collection());
        $context = new BusinessToolContext('test-tenant', $user);
        $result = $registry->execute('revenue_summary', $context);
        $this->assertTrue($result->isDenied());
        $this->assertSame('revenue_summary', $result->tool);
    }

    public function test_execution_error_for_unregistered(): void
    {
        $registry = new BusinessToolRegistry();
        $user = Mockery::mock(\App\Models\Tenant\User::class);
        $user->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $context = new BusinessToolContext('test-tenant', $user);
        $result = $registry->execute('nonexistent', $context);
        $this->assertTrue($result->isError());
        $this->assertStringContainsString('not registered', $result->error);
    }

    public function test_catches_tool_exceptions(): void
    {
        $registry = new BusinessToolRegistry();
        $throwingTool = new class implements SafeBusinessTool {
            public function name(): string { return 'thrower'; }
            public function description(): string { return 'Throws'; }
            public function version(): string { return '1.0.0'; }
            public function requiredPermissions(): array { return []; }
            public function supports(string $intent): bool { return true; }
            public function execute(BusinessToolContext $context): BusinessToolResult { throw new \RuntimeException('Simulated failure'); }
        };
        $registry->register($throwingTool);
        $user = Mockery::mock(\App\Models\Tenant\User::class);
        $user->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user->shouldReceive('getAttribute')->with('branch_id')->andReturn(1);
        $user->shouldReceive('getAttribute')->with('roles')->andReturn(new Collection());
        $context = new BusinessToolContext('test-tenant', $user);
        $result = $registry->execute('thrower', $context);
        $this->assertTrue($result->isError());
        $this->assertSame('Simulated failure', $result->error);
    }
}
