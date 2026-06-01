<?php

namespace Tests\Feature;

use App\Enums\RentalReturnSettlementStatus;
use App\Enums\SecurityDepositStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Account;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Dress;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\JournalEntryLine;
use App\Models\Tenant\Permission;
use App\Models\Tenant\RentalReturnSettlement;
use App\Models\Tenant\Role;
use App\Models\Tenant\SecurityDepositTransaction;
use App\Models\Tenant\User;
use App\Services\Tenant\SecurityDepositService;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantRentalReturnSettlementTest extends TestCase
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
            'invoice_delivery.deliver',
            'invoice_delivery.return',
            'invoices.view',
            'invoices.create',
        ]);
    }

    public function test_preview_on_delivered_rental_returns_late_days_and_suggested_fees(): void
    {
        [$invoice] = $this->createDeliveredRentInvoice(rentEndDate: '2026-06-01');
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson(
            "/api/tenant/returns/{$invoice->id}/settlement-preview?".http_build_query([
                'returned_at' => '2026-06-05',
                'condition' => 'good',
            ]),
            $this->tenantHeaders(),
        );

        $response->assertOk()
            ->assertJsonPath('data.late_days', 4)
            ->assertJsonPath('data.condition', 'good');
        $this->assertGreaterThan(0, (float) $response->json('data.suggested_late_fee'));
    }

    public function test_preview_rejects_non_rent_invoice(): void
    {
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-SELL-'.uniqid(),
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_DELIVERED,
            'total' => 100,
            'remaining_amount' => 0,
            'paid_amount' => 100,
        ]);
        Sanctum::actingAs($this->user, ['*']);

        $this->getJson(
            "/api/tenant/returns/{$invoice->id}/settlement-preview?returned_at=2026-06-05",
            $this->tenantHeaders(),
        )->assertStatus(422)
            ->assertJsonPath('errors.invoice.0', 'التسوية متاحة فقط لفواتير الإيجار');
    }

    public function test_preview_rejects_undelivered_rental(): void
    {
        $invoice = $this->createRentInvoiceRecord(delivered: false);
        Sanctum::actingAs($this->user, ['*']);

        $this->getJson(
            "/api/tenant/returns/{$invoice->id}/settlement-preview?returned_at=2026-06-05",
            $this->tenantHeaders(),
        )->assertStatus(422)
            ->assertJsonPath('errors.invoice.0', 'يجب تسليم الفستان قبل تسوية الإرجاع');
    }

    public function test_settle_on_time_good_return_persists_settlement_without_late_fee(): void
    {
        [$invoice, $dress] = $this->createDeliveredRentInvoice(rentEndDate: '2026-06-10');
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/returns/{$invoice->id}/settle", [
            'returned_at' => '2026-06-10',
            'condition' => 'good',
            'late_fee' => 0,
            'damage_fee' => 0,
            'cleaning_fee' => 0,
            'deposit_refund_amount' => 0,
        ], $this->tenantHeaders())->assertCreated();

        $this->assertDatabaseHas('rental_return_settlements', [
            'invoice_id' => $invoice->id,
            'status' => RentalReturnSettlementStatus::SETTLED->value,
            'late_days' => 0,
            'late_fee' => 0,
        ], 'tenant');

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_RETURNED, $invoice->status);
        $this->assertDatabaseHas('dresses', ['id' => $dress->id, 'status' => Dress::STATUS_AVAILABLE], 'tenant');
    }

    public function test_late_return_calculates_late_days_and_persists_late_fee(): void
    {
        [$invoice] = $this->createDeliveredRentInvoice(rentEndDate: '2026-06-01');
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/returns/{$invoice->id}/settle", [
            'returned_at' => '2026-06-04',
            'condition' => 'good',
            'late_fee' => 120,
            'damage_fee' => 0,
            'cleaning_fee' => 0,
            'deposit_refund_amount' => 0,
        ], $this->tenantHeaders())->assertCreated();

        $this->assertDatabaseHas('rental_return_settlements', [
            'invoice_id' => $invoice->id,
            'late_days' => 3,
            'late_fee' => 120,
            'total_fees' => 120,
        ], 'tenant');
    }

    public function test_damaged_return_moves_dress_to_maintenance_and_posts_fee_journal_when_deposit_held(): void
    {
        [$invoice, $dress] = $this->createDeliveredRentInvoice(
            rentEndDate: '2026-06-10',
            depositPaid: 500,
        );
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/returns/{$invoice->id}/settle", [
            'returned_at' => '2026-06-10',
            'condition' => 'damaged',
            'late_fee' => 0,
            'damage_fee' => 300,
            'cleaning_fee' => 0,
            'deposit_refund_amount' => 200,
        ], $this->tenantHeaders())->assertCreated();

        $this->assertDatabaseHas('dresses', ['id' => $dress->id, 'status' => Dress::STATUS_MAINTENANCE], 'tenant');

        $settlement = RentalReturnSettlement::query()->where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($settlement);
        $this->assertNotNull($settlement->journal_entry_id);

        $entry = JournalEntry::query()->find($settlement->journal_entry_id);
        $this->assertTrue($entry->is_balanced);
        $this->assertJournalCreditsAccount($entry, '4210', 300.0);
    }

    public function test_deposit_refund_posts_liability_debit_and_cash_credit(): void
    {
        [$invoice] = $this->createDeliveredRentInvoice(
            rentEndDate: '2026-06-10',
            depositPaid: 500,
        );
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/returns/{$invoice->id}/settle", [
            'returned_at' => '2026-06-10',
            'condition' => 'good',
            'late_fee' => 0,
            'damage_fee' => 0,
            'cleaning_fee' => 0,
            'deposit_refund_amount' => 500,
        ], $this->tenantHeaders())->assertCreated();

        $this->assertDatabaseHas('rental_return_settlements', [
            'invoice_id' => $invoice->id,
            'deposit_refund_amount' => 500,
            'deposit_withheld_amount' => 0,
        ], 'tenant');

        $refundTx = SecurityDepositTransaction::query()
            ->where('invoice_id', $invoice->id)
            ->where('type', SecurityDepositTransaction::TYPE_REFUNDED)
            ->first();
        $this->assertNotNull($refundTx);

        $this->assertDatabaseHas('cash_movements', [
            'reference_type' => CashMovement::REFERENCE_SECURITY_DEPOSIT_TRANSACTION,
            'reference_id' => $refundTx->id,
            'type' => CashMovement::TYPE_SECURITY_DEPOSIT_REFUND,
        ], 'tenant');

        $settlement = RentalReturnSettlement::query()->where('invoice_id', $invoice->id)->first();
        $entry = JournalEntry::query()->find($settlement->journal_entry_id);
        $this->assertJournalCreditsAccount($entry, '1000', 500.0);
        $this->assertJournalDebitsAccount($entry, '2100', 500.0);
    }

    public function test_deposit_withholding_and_partial_refund_are_balanced(): void
    {
        [$invoice] = $this->createDeliveredRentInvoice(
            rentEndDate: '2026-06-10',
            depositPaid: 500,
        );
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/returns/{$invoice->id}/settle", [
            'returned_at' => '2026-06-10',
            'condition' => 'damaged',
            'late_fee' => 100,
            'damage_fee' => 200,
            'cleaning_fee' => 0,
            'deposit_refund_amount' => 200,
        ], $this->tenantHeaders())->assertCreated();

        $this->assertDatabaseHas('rental_return_settlements', [
            'invoice_id' => $invoice->id,
            'deposit_withheld_amount' => 300,
            'deposit_refund_amount' => 200,
            'additional_amount_due' => 0,
        ], 'tenant');

        $settlement = RentalReturnSettlement::query()->where('invoice_id', $invoice->id)->first();
        $entry = JournalEntry::query()->find($settlement->journal_entry_id);
        $this->assertTrue($entry->is_balanced);
        $this->assertJournalCreditsAccount($entry, '4200', 100.0);
        $this->assertJournalCreditsAccount($entry, '4210', 200.0);
    }

    public function test_additional_amount_due_when_fees_exceed_deposit_paid(): void
    {
        [$invoice] = $this->createDeliveredRentInvoice(
            rentEndDate: '2026-06-10',
            depositPaid: 200,
        );
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/returns/{$invoice->id}/settle", [
            'returned_at' => '2026-06-10',
            'condition' => 'damaged',
            'late_fee' => 0,
            'damage_fee' => 400,
            'cleaning_fee' => 0,
            'deposit_refund_amount' => 0,
        ], $this->tenantHeaders())->assertCreated();

        $this->assertDatabaseHas('rental_return_settlements', [
            'invoice_id' => $invoice->id,
            'deposit_withheld_amount' => 200,
            'additional_amount_due' => 200,
        ], 'tenant');

        $settlement = RentalReturnSettlement::query()->where('invoice_id', $invoice->id)->first();
        $entry = JournalEntry::query()->find($settlement->journal_entry_id);
        $this->assertJournalDebitsAccount($entry, '1200', 200.0);
        $this->assertEquals(0, CashMovement::query()->where('type', CashMovement::TYPE_INVOICE_PAYMENT)->count());
    }

    public function test_cannot_settle_same_invoice_twice(): void
    {
        [$invoice] = $this->createDeliveredRentInvoice(rentEndDate: '2026-06-10');
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/returns/{$invoice->id}/settle", [
            'returned_at' => '2026-06-10',
            'condition' => 'good',
            'deposit_refund_amount' => 0,
        ], $this->tenantHeaders())->assertCreated();

        $this->postJson("/api/tenant/returns/{$invoice->id}/settle", [
            'returned_at' => '2026-06-11',
            'condition' => 'good',
            'deposit_refund_amount' => 0,
        ], $this->tenantHeaders())->assertStatus(422);
    }

    public function test_legacy_return_endpoint_still_works_and_creates_zero_fee_settlement(): void
    {
        [$invoice, $dress] = $this->createDeliveredRentInvoice(rentEndDate: '2026-06-10');
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson("/api/tenant/invoices/{$invoice->id}/return", [
            'returned_at' => '2026-06-10',
        ], $this->tenantHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_RETURNED);

        $this->assertDatabaseHas('dresses', ['id' => $dress->id, 'status' => Dress::STATUS_AVAILABLE], 'tenant');
        $this->assertDatabaseHas('rental_return_settlements', [
            'invoice_id' => $invoice->id,
            'status' => RentalReturnSettlementStatus::SETTLED->value,
            'total_fees' => 0,
        ], 'tenant');
    }

    public function test_user_without_return_permission_cannot_settle(): void
    {
        [$invoice] = $this->createDeliveredRentInvoice(rentEndDate: '2026-06-10');
        $viewer = $this->createTenantUserWithPermissions(['invoices.view']);
        Sanctum::actingAs($viewer, ['*']);

        $this->postJson("/api/tenant/returns/{$invoice->id}/settle", [
            'returned_at' => '2026-06-10',
            'condition' => 'good',
        ], $this->tenantHeaders())->assertStatus(403);
    }

    private function assertJournalCreditsAccount(JournalEntry $entry, string $code, float $amount): void
    {
        $accountId = Account::query()->where('code', $code)->value('id');
        $credit = (float) JournalEntryLine::query()
            ->where('journal_entry_id', $entry->id)
            ->where('account_id', $accountId)
            ->sum('credit');
        $this->assertEqualsWithDelta($amount, $credit, 0.01);
    }

    private function assertJournalDebitsAccount(JournalEntry $entry, string $code, float $amount): void
    {
        $accountId = Account::query()->where('code', $code)->value('id');
        $debit = (float) JournalEntryLine::query()
            ->where('journal_entry_id', $entry->id)
            ->where('account_id', $accountId)
            ->sum('debit');
        $this->assertEqualsWithDelta($amount, $debit, 0.01);
    }

    /**
     * @return array{0: Invoice, 1: Dress}
     */
    private function createDeliveredRentInvoice(
        string $rentEndDate = '2026-06-05',
        float $depositPaid = 0,
    ): array {
        $invoice = $this->createRentInvoiceRecord(depositPaid: $depositPaid, rentEndDate: $rentEndDate);
        Sanctum::actingAs($this->user, ['*']);
        $this->postJson("/api/tenant/invoices/{$invoice->id}/deliver", [], $this->tenantHeaders())->assertOk();

        if ($depositPaid > 0) {
            app(SecurityDepositService::class)->recordCollection($invoice->refresh(), [
                'amount' => $depositPaid,
                'method' => 'cash',
            ], $this->user->id);
        }

        $dress = Dress::query()->find($invoice->items()->first()->dress_id);

        return [$invoice->refresh(), $dress];
    }

    private function createRentInvoiceRecord(
        bool $delivered = false,
        float $depositPaid = 0,
        string $rentEndDate = '2026-06-05',
    ): Invoice {
        $customer = Customer::query()->create(['name' => 'Renter', 'status' => 'active']);
        $dress = Dress::query()->create([
            'code' => 'DR-RST-'.uniqid(),
            'name' => 'Return Dress',
            'status' => Dress::STATUS_AVAILABLE,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-RST-'.uniqid(),
            'customer_id' => $customer->id,
            'type' => Invoice::TYPE_RENT,
            'status' => $delivered ? Invoice::STATUS_DELIVERED : Invoice::STATUS_CONFIRMED,
            'rent_start_date' => '2026-06-01',
            'rent_end_date' => $rentEndDate,
            'delivery_date' => $delivered ? '2026-06-01' : null,
            'days_of_rent' => 5,
            'security_deposit' => $depositPaid > 0 ? 500 : 0,
            'deposit_paid_amount' => 0,
            'security_deposit_status' => $depositPaid > 0 ? SecurityDepositStatus::NONE->value : null,
            'subtotal' => 300,
            'total' => 300,
            'paid_amount' => 0,
            'remaining_amount' => 300,
        ]);

        $invoice->items()->create([
            'dress_id' => $dress->id,
            'quantity' => 1,
            'unit_price' => 300,
            'total' => 300,
        ]);

        return $invoice->refresh();
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-rental-return-settlement.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-rental-return-settlement.sqlite';

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
        Artisan::call('migrate:fresh', ['--database' => 'central', '--force' => true]);
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
        $role = Role::query()->create(['name' => 'Role '.uniqid(), 'slug' => 'role-'.uniqid()]);
        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();
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
