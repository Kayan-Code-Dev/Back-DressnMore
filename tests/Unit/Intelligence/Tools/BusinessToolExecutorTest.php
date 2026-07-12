<?php

namespace Tests\Unit\Intelligence\Tools;

use App\Models\Tenant\User;
use App\Services\Intelligence\Tools\BusinessToolExecutor;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class BusinessToolExecutorTest extends TestCase
{
    private function mockUser(): User
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user->shouldReceive('getAttribute')->with('branch_id')->andReturn(1);
        $user->shouldReceive('getAttribute')->with('roles')->andReturn(new Collection());
        return $user;
    }

    public function test_unsupported_question_returns_not_handled(): void
    {
        $executor = new BusinessToolExecutor();
        $result = $executor->tryAnswer($this->mockUser(), 'asdfghjkl random text 12345', 'test-tenant');
        $this->assertFalse($result['handled']);
        $this->assertSame([], $result['tools_executed']);
    }

    public function test_arabic_revenue_question_is_handled(): void
    {
        $executor = new BusinessToolExecutor();
        $result = $executor->tryAnswer($this->mockUser(), 'كم الإيرادات هذا الشهر؟', 'test-tenant');
        $this->assertTrue($result['handled']);
        $this->assertContains('revenue_summary', $result['tools_executed']);
    }

    public function test_english_snapshot_question_is_handled(): void
    {
        $executor = new BusinessToolExecutor();
        $result = $executor->tryAnswer($this->mockUser(), 'How is business doing today?', 'test-tenant');
        $this->assertTrue($result['handled']);
        $this->assertContains('business_snapshot', $result['tools_executed']);
    }

    public function test_composite_health_question_is_handled(): void
    {
        $executor = new BusinessToolExecutor();
        $result = $executor->tryAnswer($this->mockUser(), 'صحة العمل ومؤشرات الأداء', 'test-tenant');
        $this->assertTrue($result['handled']);
        $this->assertContains('business_health', $result['tools_executed']);
    }

    public function test_daily_brief_arabic(): void
    {
        $executor = new BusinessToolExecutor();
        $result = $executor->tryAnswer($this->mockUser(), 'تقرير اليوم', 'test-tenant');
        $this->assertTrue($result['handled']);
        $this->assertContains('daily_brief', $result['tools_executed']);
    }

    public function test_tool_metadata_returns_all_tools(): void
    {
        $executor = new BusinessToolExecutor();
        $meta = $executor->toolMetadata();
        $this->assertCount(9, $meta);
        $names = array_column($meta, 'name');
        $this->assertContains('revenue_summary', $names);
        $this->assertContains('late_returns', $names);
        $this->assertContains('daily_brief', $names);
    }

    public function test_execution_timing_present(): void
    {
        $executor = new BusinessToolExecutor();
        $result = $executor->tryAnswer($this->mockUser(), 'كم الإيرادات؟', 'test-tenant');
        $this->assertTrue($result['handled']);
        $this->assertArrayHasKey('execution_ms', $result);
        $this->assertIsInt($result['execution_ms']);
        $this->assertGreaterThanOrEqual(0, $result['execution_ms']);
    }
}
