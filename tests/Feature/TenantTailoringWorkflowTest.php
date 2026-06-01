<?php

namespace Tests\Feature;

use App\Enums\TailoringPriority;
use App\Enums\TailoringProductionStage;
use App\Enums\TailoringProductionStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\TailoringStageHistory;
use App\Models\Tenant\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantTailoringWorkflowTest extends TestCase
{
    private string $centralDatabasePath;

    private string $tenantDatabasePath;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSqliteDatabases();
        $this->runMigrations();
        $this->seedTenantPermissions();
        $this->tenant = $this->createTenant();
        $this->user = $this->createTenantUserWithPermissions([
            'tailoring.view',
            'tailoring.create',
            'tailoring.update',
            'tailoring.change_stage',
            'tailoring.override_stage',
            'tailoring.view_workshop',
            'tailoring.view_schedule',
            'invoices.create',
            'invoices.view',
        ]);
    }

    public function test_create_tailoring_order_with_persisted_stage_and_status(): void
    {
        $customer = Customer::query()->create(['name' => 'Tailoring Client', 'status' => 'active']);
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/tenant/tailoring/orders', [
            'customer_id' => $customer->id,
            'priority' => TailoringPriority::HIGH->value,
            'fitting_date' => '2026-07-01',
            'design_notes' => 'Wide sleeves',
            'items' => [
                ['description' => 'Custom abaya', 'quantity' => 1, 'unit_price' => 500],
            ],
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.current_stage', TailoringProductionStage::NEW_ORDER->value)
            ->assertJsonPath('data.production_status', TailoringProductionStatus::PENDING->value)
            ->assertJsonPath('data.priority', TailoringPriority::HIGH->value)
            ->assertJsonPath('data.design_notes', 'Wide sleeves');

        $invoiceId = (int) $response->json('data.id');
        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'production_stage' => TailoringProductionStage::NEW_ORDER->value,
            'production_status' => TailoringProductionStatus::PENDING->value,
            'priority' => TailoringPriority::HIGH->value,
        ], 'tenant');

        $this->assertSame(1, TailoringStageHistory::query()->where('invoice_id', $invoiceId)->count());
    }

    public function test_list_filters_by_stage_status_and_priority(): void
    {
        $this->seedTailoringInvoice(stage: TailoringProductionStage::SEWING->value, priority: TailoringPriority::URGENT->value);
        $this->seedTailoringInvoice(stage: TailoringProductionStage::NEW_ORDER->value, priority: TailoringPriority::NORMAL->value);

        Sanctum::actingAs($this->user, ['*']);

        $this->getJson('/api/tenant/tailoring/orders?production_stage=sewing', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.current_stage', 'sewing');

        $this->getJson('/api/tenant/tailoring/orders?priority=urgent', $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.priority', 'urgent');
    }

    public function test_details_returns_persisted_tailoring_fields(): void
    {
        $invoice = $this->seedTailoringInvoice(
            stage: TailoringProductionStage::FABRIC_CUTTING->value,
            status: TailoringProductionStatus::IN_PROGRESS->value,
            priority: TailoringPriority::HIGH->value,
        );
        $invoice->update([
            'design_notes' => 'Gold embroidery',
            'workshop_notes' => 'Handle fabric carefully',
            'tailoring_measurements' => [
                ['id' => 1, 'label' => 'Chest', 'value' => '90', 'unit' => 'cm'],
            ],
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->getJson("/api/tenant/tailoring/orders/{$invoice->id}", $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.current_stage', 'fabric_cutting')
            ->assertJsonPath('data.production_status', 'in_progress')
            ->assertJsonPath('data.design_notes', 'Gold embroidery')
            ->assertJsonPath('data.workshop_notes', 'Handle fabric carefully')
            ->assertJsonPath('data.measurements.0.label', 'Chest');
    }

    public function test_change_stage_creates_history(): void
    {
        $invoice = $this->seedTailoringInvoice();
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/tailoring/orders/{$invoice->id}/change-stage", [
            'to_stage' => TailoringProductionStage::FABRIC_CUTTING->value,
            'to_status' => TailoringProductionStatus::IN_PROGRESS->value,
            'notes' => 'Started cutting',
        ], $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.current_stage', 'fabric_cutting')
            ->assertJsonPath('data.production_status', 'in_progress');

        $this->assertDatabaseHas('tailoring_stage_histories', [
            'invoice_id' => $invoice->id,
            'from_stage' => TailoringProductionStage::NEW_ORDER->value,
            'to_stage' => TailoringProductionStage::FABRIC_CUTTING->value,
            'notes' => 'Started cutting',
        ], 'tenant');

        $this->getJson("/api/tenant/tailoring/orders/{$invoice->id}/stage-history", $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.to_stage', TailoringProductionStage::FABRIC_CUTTING->value);
    }

    public function test_cannot_change_stage_on_non_tailoring_invoice(): void
    {
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-SELL-'.uniqid(),
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'total' => 100,
            'remaining_amount' => 100,
            'paid_amount' => 0,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/tailoring/orders/{$invoice->id}/change-stage", [
            'to_stage' => TailoringProductionStage::SEWING->value,
        ], $this->tenantHeaders())->assertNotFound();
    }

    public function test_cannot_change_stage_without_permission(): void
    {
        $invoice = $this->seedTailoringInvoice();
        $viewer = $this->createTenantUserWithPermissions(['tailoring.view']);
        Sanctum::actingAs($viewer, ['*']);

        $this->postJson("/api/tenant/tailoring/orders/{$invoice->id}/change-stage", [
            'to_stage' => TailoringProductionStage::SEWING->value,
        ], $this->tenantHeaders())->assertStatus(403);
    }

    public function test_cannot_move_backward_without_override_permission(): void
    {
        $invoice = $this->seedTailoringInvoice(stage: TailoringProductionStage::SEWING->value);
        $user = $this->createTenantUserWithPermissions([
            'tailoring.view',
            'tailoring.change_stage',
        ]);
        Sanctum::actingAs($user, ['*']);

        $this->postJson("/api/tenant/tailoring/orders/{$invoice->id}/change-stage", [
            'to_stage' => TailoringProductionStage::NEW_ORDER->value,
        ], $this->tenantHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.to_stage.0', 'لا يمكن الرجوع لمرحلة سابقة بدون صلاحية تجاوز المرحلة');
    }

    public function test_can_move_backward_with_override_permission(): void
    {
        $invoice = $this->seedTailoringInvoice(stage: TailoringProductionStage::SEWING->value);
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/tailoring/orders/{$invoice->id}/change-stage", [
            'to_stage' => TailoringProductionStage::FABRIC_CUTTING->value,
        ], $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.current_stage', 'fabric_cutting');
    }

    public function test_workshop_board_groups_by_stage(): void
    {
        $this->seedTailoringInvoice(stage: TailoringProductionStage::SEWING->value);
        $this->seedTailoringInvoice(stage: TailoringProductionStage::NEW_ORDER->value);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/tenant/tailoring/workshop-board', $this->tenantHeaders())
            ->assertOk();

        $columns = collect($response->json('data'));
        $sewing = $columns->firstWhere('stage', 'sewing');
        $newOrder = $columns->firstWhere('stage', 'new_order');

        $this->assertSame(1, $sewing['count']);
        $this->assertSame(1, $newOrder['count']);
        $this->assertSame('خياطة', $sewing['label']);
    }

    public function test_schedule_endpoint_returns_fitting_dates(): void
    {
        $invoice = $this->seedTailoringInvoice();
        $invoice->update(['fitting_date' => '2026-08-15']);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/tenant/tailoring/schedule', $this->tenantHeaders())
            ->assertOk();

        $fittingDates = collect($response->json('data.fitting_dates'));
        $this->assertTrue($fittingDates->contains(fn (array $row): bool => $row['date'] === '2026-08-15'));
    }

    public function test_stage_is_persisted_not_modulo_based(): void
    {
        $invoiceA = $this->seedTailoringInvoice(stage: TailoringProductionStage::FIRST_FITTING->value);
        $invoiceB = Invoice::query()->create([
            'invoice_number' => 'T-'.uniqid(),
            'type' => Invoice::TYPE_TAILORING,
            'status' => Invoice::STATUS_CONFIRMED,
            'production_stage' => TailoringProductionStage::ADJUSTMENTS->value,
            'production_status' => TailoringProductionStatus::IN_PROGRESS->value,
            'priority' => TailoringPriority::NORMAL->value,
            'total' => 300,
            'remaining_amount' => 300,
            'paid_amount' => 0,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $this->getJson("/api/tenant/tailoring/orders/{$invoiceA->id}", $this->tenantHeaders())
            ->assertJsonPath('data.current_stage', 'first_fitting');

        $this->getJson("/api/tenant/tailoring/orders/{$invoiceB->id}", $this->tenantHeaders())
            ->assertJsonPath('data.current_stage', 'adjustments');
    }

    public function test_cannot_mark_ready_for_delivery_without_measurements(): void
    {
        $invoice = $this->seedTailoringInvoice(stage: TailoringProductionStage::SEWING->value);
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/tailoring/orders/{$invoice->id}/change-stage", [
            'to_stage' => TailoringProductionStage::READY_FOR_DELIVERY->value,
        ], $this->tenantHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.to_stage.0', 'يجب تسجيل المقاسات قبل وضع الطلب جاهزاً للتسليم');
    }

    public function test_tailoring_create_with_initial_payment_posts_payment(): void
    {
        $customer = Customer::query()->create(['name' => 'Tailoring Pay Client', 'status' => 'active']);
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/tenant/tailoring/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['description' => 'Custom dress', 'quantity' => 1, 'unit_price' => 400],
            ],
            'initial_payment' => [
                'amount' => 100,
                'method' => 'cash',
            ],
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.current_stage', TailoringProductionStage::NEW_ORDER->value)
            ->assertJsonPath('data.paid', 100);

        $invoiceId = (int) $response->json('data.id');
        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoiceId,
            'amount' => 100,
        ], 'tenant');
    }

    private function seedTailoringInvoice(
        string $stage = 'new_order',
        string $status = 'pending',
        string $priority = 'normal',
    ): Invoice {
        return Invoice::query()->create([
            'invoice_number' => 'T-'.uniqid(),
            'type' => Invoice::TYPE_TAILORING,
            'status' => Invoice::STATUS_CONFIRMED,
            'production_stage' => $stage,
            'production_status' => $status,
            'priority' => $priority,
            'total' => 250,
            'remaining_amount' => 250,
            'paid_amount' => 0,
        ]);
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-tailoring.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-tailoring.sqlite';

        @unlink($this->centralDatabasePath);
        @unlink($this->tenantDatabasePath);

        touch($this->centralDatabasePath);
        touch($this->tenantDatabasePath);

        Config::set('database.default', 'central');
        Config::set('database.connections.central', [
            'driver' => 'sqlite',
            'database' => $this->centralDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        Config::set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('central');
        DB::purge('tenant');
    }

    private function runMigrations(): void
    {
        Artisan::call('migrate:fresh', [
            '--database' => 'central',
            '--force' => true,
        ]);

        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => base_path('database/migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    private function seedTenantPermissions(): void
    {
        Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => TenantRolePermissionSeeder::class,
            '--force' => true,
        ]);
    }

    private function createTenant(): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Demo Tenant',
            'slug' => 'demo',
            'database_name' => $this->tenantDatabasePath,
            'status' => 'active',
            'subscription_starts_at' => CarbonImmutable::now()->subDay(),
            'subscription_ends_at' => CarbonImmutable::now()->addDays(10),
        ]);
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function createTenantUserWithPermissions(array $permissionKeys): User
    {
        $role = Role::query()->create([
            'name' => 'Role '.uniqid(),
            'slug' => 'role-'.uniqid(),
        ]);

        $permissionIds = Permission::query()
            ->whereIn('key', $permissionKeys)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);

        $user = User::query()->create([
            'name' => 'Tenant User '.uniqid(),
            'email' => uniqid().'@tenant.test',
            'password' => 'password',
            'status' => 'active',
        ]);

        $user->roles()->sync([$role->id]);

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function tenantHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'X-Tenant' => $this->tenant->slug,
        ];
    }
}
