<?php

namespace Tests\Feature;

use App\Models\Central\PaymentGateway;
use App\Models\Central\Plan;
use App\Models\Central\PlanRequest;
use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use Database\Seeders\Central\PlanSeeder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformPlanRequestTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantTemplateDatabasePath;

    private SuperAdmin $admin;

    private Plan $plan;

    private Plan $freePlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSqliteDatabases();
        $this->runCentralMigrations();
        $this->seedPlans();
        $this->admin = $this->createSuperAdmin();
    }

    public function test_public_can_list_plans_via_v1_endpoint(): void
    {
        $response = $this->getJson('/api/v1/plans?page=1', [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'title', 'price', 'days', 'is_active'],
                ],
                'current_page',
                'total',
            ]);
    }

    public function test_public_can_submit_paid_plan_request(): void
    {
        $gateway = $this->createPaymentGateway();

        $response = $this->postJson('/api/v1/order-plans', [
            'plan_id' => $this->plan->id,
            'name' => 'Atelier Owner',
            'email' => 'owner@example.com',
            'password' => 'SecurePass1',
            'phone' => '0500000000',
            'company_name' => 'Atelier One',
            'payment_gateway_id' => $gateway->id,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.auto_provisioned', false);

        $this->assertDatabaseHas('plan_requests', [
            'email' => 'owner@example.com',
            'status' => 'pending',
            'plan_id' => $this->plan->id,
        ], 'central');
    }

    public function test_public_free_plan_request_is_auto_provisioned(): void
    {
        $tenantDatabasePath = storage_path('framework/testing/free-plan-tenant.sqlite');
        @unlink($tenantDatabasePath);

        $response = $this->postJson('/api/v1/order-plans', [
            'plan_id' => $this->freePlan->id,
            'name' => 'Free Owner',
            'email' => 'free-owner@example.com',
            'password' => 'SecurePass1',
            'phone' => '0500000001',
            'company_name' => 'Free Atelier',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.auto_provisioned', true)
            ->assertJsonPath('data.login.email', 'free-owner@example.com');

        $this->assertDatabaseHas('plan_requests', [
            'email' => 'free-owner@example.com',
            'status' => 'approved',
        ], 'central');

        $this->assertDatabaseHas('tenants', [
            'name' => 'Free Atelier',
            'status' => 'active',
        ], 'central');
    }

    public function test_platform_admin_can_approve_pending_plan_request(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $gateway = $this->createPaymentGateway();
        $planRequest = PlanRequest::query()->create([
            'plan_id' => $this->plan->id,
            'name' => 'Pending Owner',
            'email' => 'pending-owner@example.com',
            'phone' => '0500000002',
            'password' => Hash::make('SecurePass1'),
            'provision_password' => Crypt::encryptString('SecurePass1'),
            'company_name' => 'Pending Atelier',
            'payment_gateway_id' => $gateway->id,
            'status' => 'pending',
        ]);

        $response = $this->patchJson("/api/platform/order-plans/{$planRequest->id}", [
            'status' => 'approved',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.admin.email', 'pending-owner@example.com')
            ->assertJsonPath('data.admin.password', 'SecurePass1')
            ->assertJsonPath('data.tenant.name', 'Pending Atelier');

        $planRequest->refresh();
        $this->assertSame('approved', $planRequest->status);
        $this->assertNotNull($planRequest->tenant_id);
        $this->assertNotNull($planRequest->subscription_id);

        $tenant = Tenant::query()->findOrFail($planRequest->tenant_id);
        $this->assertSame('active', $tenant->status);
        $this->assertNotNull($tenant->subscription_starts_at);
        $this->assertNotNull($tenant->subscription_ends_at);
    }

    public function test_platform_admin_can_reject_pending_plan_request(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $planRequest = PlanRequest::query()->create([
            'plan_id' => $this->plan->id,
            'name' => 'Reject Owner',
            'email' => 'reject-owner@example.com',
            'phone' => '0500000003',
            'password' => Hash::make('SecurePass1'),
            'company_name' => 'Reject Atelier',
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/platform/order-plans/{$planRequest->id}/reject", [
            'admin_notes' => 'Incomplete payment proof',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('plan_requests', [
            'id' => $planRequest->id,
            'status' => 'rejected',
            'admin_notes' => 'Incomplete payment proof',
        ], 'central');
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-plan-requests.sqlite';
        $this->tenantTemplateDatabasePath = $testingPath.'/tenant-template-plan-requests.sqlite';

        @unlink($this->centralDatabasePath);
        @unlink($this->tenantTemplateDatabasePath);

        touch($this->centralDatabasePath);
        touch($this->tenantTemplateDatabasePath);

        Config::set('database.default', 'central');
        Config::set('database.connections.central', [
            'driver' => 'sqlite',
            'database' => $this->centralDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        Config::set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantTemplateDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('central');
        DB::purge('tenant');
    }

    private function runCentralMigrations(): void
    {
        Artisan::call('migrate:fresh', [
            '--database' => 'central',
            '--force' => true,
        ]);
    }

    private function seedPlans(): void
    {
        Artisan::call('db:seed', [
            '--database' => 'central',
            '--class' => PlanSeeder::class,
            '--force' => true,
        ]);

        $this->plan = Plan::query()->where('slug', 'basic')->firstOrFail();

        $this->freePlan = Plan::query()->create([
            'name' => 'Free Trial',
            'slug' => 'free-trial',
            'price' => 0,
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'sort_order' => 0,
            'status' => 'active',
            'description' => 'Free plan for testing',
        ]);
    }

    private function createSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'platform@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    private function createPaymentGateway(): PaymentGateway
    {
        return PaymentGateway::query()->create([
            'name' => 'Bank Transfer',
            'type' => 'bank',
            'account_holder' => 'DressnMore',
            'account_number' => '1234567890',
            'bank_name' => 'Test Bank',
            'instructions' => 'Transfer then upload proof',
            'is_active' => true,
            'display_order' => 1,
        ]);
    }
}
