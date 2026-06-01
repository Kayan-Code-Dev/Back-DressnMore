<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\SecurityDepositStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Account;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Dress;
use App\Models\Tenant\DressCategory;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoicePayment;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\JournalEntryLine;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\SecurityDepositTransaction;
use App\Models\Tenant\User;
use App\Services\Tenant\JournalEntryPostingService;
use Carbon\CarbonImmutable;
use Database\Seeders\Tenant\TenantRolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantInvoiceInitialPaymentTest extends TestCase
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
            'invoices.create',
            'invoices.view',
        ]);
    }

    public function test_sale_initial_payment_creates_payment_cash_movement_and_balanced_journal(): void
    {
        $customer = Customer::query()->create(['name' => 'Buyer', 'status' => 'active']);
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/tenant/sales/invoices', [
            'customer_id' => $customer->id,
            'items' => [
                ['description' => 'Sale dress', 'quantity' => 1, 'unit_price' => 200],
            ],
            'initial_payment' => [
                'amount' => 80,
                'method' => 'cash',
            ],
        ], $this->tenantHeaders());

        $response->assertCreated();
        $invoiceId = (int) $response->json('data.id');

        $payment = InvoicePayment::query()->where('invoice_id', $invoiceId)->first();
        $this->assertNotNull($payment);
        $this->assertSame(PaymentStatus::PAID->value, $payment->status);

        $this->assertDatabaseHas('cash_movements', [
            'reference_type' => CashMovement::REFERENCE_INVOICE_PAYMENT,
            'reference_id' => $payment->id,
        ], 'tenant');

        $entry = JournalEntry::query()
            ->where('source_type', JournalEntry::SOURCE_PAYMENT)
            ->where('source_id', $payment->id)
            ->first();
        $this->assertNotNull($entry);
        $this->assertTrue($entry->is_balanced);
        $this->assertJournalCreditsAccount($entry, '4100', 80.0);

        $response->assertJsonPath('data.paid_amount', '80.00')
            ->assertJsonPath('data.remaining_amount', '120.00');
    }

    public function test_rental_initial_payment_posts_rental_revenue_not_deposit_liability(): void
    {
        [$dress, $category, $subcategory] = $this->createRentableDress();
        $customer = Customer::query()->create(['name' => 'Renter', 'status' => 'active']);
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/tenant/invoices', [
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_CONFIRMED,
            'customer_id' => $customer->id,
            'rent_start_date' => '2026-06-01',
            'rent_end_date' => '2026-06-05',
            'security_deposit' => 500,
            'items' => [
                [
                    'dress_id' => $dress->id,
                    'quantity' => 1,
                    'unit_price' => 300,
                ],
            ],
            'initial_payment' => [
                'amount' => 100,
                'method' => 'cash',
            ],
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.paid_amount', '100.00')
            ->assertJsonPath('data.remaining_amount', '200.00')
            ->assertJsonPath('data.deposit_paid_amount', '0.00');

        $payment = InvoicePayment::query()->where('invoice_id', $response->json('data.id'))->first();
        $this->assertNotNull($payment);

        $entry = JournalEntry::query()
            ->where('source_type', JournalEntry::SOURCE_PAYMENT)
            ->where('source_id', $payment->id)
            ->first();
        $this->assertJournalCreditsAccount($entry, '4000', 100.0);
        $this->assertJournalDoesNotCreditAccount($entry, '2100');
    }

    public function test_rental_security_deposit_payment_posts_liability_and_updates_deposit_fields(): void
    {
        [$dress] = $this->createRentableDress();
        $customer = Customer::query()->create(['name' => 'Renter 2', 'status' => 'active']);
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/tenant/invoices', [
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_CONFIRMED,
            'customer_id' => $customer->id,
            'rent_start_date' => '2026-06-01',
            'rent_end_date' => '2026-06-05',
            'security_deposit' => 500,
            'items' => [
                ['dress_id' => $dress->id, 'quantity' => 1, 'unit_price' => 300],
            ],
            'security_deposit_payment' => [
                'amount' => 500,
                'method' => 'cash',
            ],
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.paid_amount', '0.00')
            ->assertJsonPath('data.deposit_paid_amount', '500.00')
            ->assertJsonPath('data.security_deposit_status', SecurityDepositStatus::HELD->value);

        $invoiceId = (int) $response->json('data.id');
        $this->assertSame(0, InvoicePayment::query()->where('invoice_id', $invoiceId)->count());

        $transaction = SecurityDepositTransaction::query()
            ->where('invoice_id', $invoiceId)
            ->where('type', SecurityDepositTransaction::TYPE_COLLECTED)
            ->first();
        $this->assertNotNull($transaction);

        $entry = JournalEntry::query()
            ->where('source_type', JournalEntry::SOURCE_SECURITY_DEPOSIT_COLLECTION)
            ->where('source_id', $transaction->id)
            ->first();
        $this->assertNotNull($entry);
        $this->assertTrue($entry->is_balanced);
        $this->assertJournalCreditsAccount($entry, '2100', 500.0);
        $this->assertJournalDoesNotCreditAccount($entry, '4000');
    }

    public function test_mixed_rental_invoice_and_deposit_payments_are_separate(): void
    {
        [$dress] = $this->createRentableDress();
        $customer = Customer::query()->create(['name' => 'Renter 3', 'status' => 'active']);
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/tenant/invoices', [
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_CONFIRMED,
            'customer_id' => $customer->id,
            'rent_start_date' => '2026-06-01',
            'rent_end_date' => '2026-06-05',
            'security_deposit' => 500,
            'items' => [
                ['dress_id' => $dress->id, 'quantity' => 1, 'unit_price' => 300],
            ],
            'initial_payment' => ['amount' => 300, 'method' => 'cash'],
            'security_deposit_payment' => ['amount' => 500, 'method' => 'cash'],
        ], $this->tenantHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.paid_amount', '300.00')
            ->assertJsonPath('data.remaining_amount', '0.00')
            ->assertJsonPath('data.deposit_paid_amount', '500.00');

        $invoiceId = (int) $response->json('data.id');
        $payment = InvoicePayment::query()->where('invoice_id', $invoiceId)->first();
        $this->assertNotNull($payment);
        $this->assertSame(300.0, (float) $payment->amount);

        $this->assertSame(
            1,
            JournalEntry::query()->where('source_type', JournalEntry::SOURCE_PAYMENT)->where('source_id', $payment->id)->count()
        );

        $transaction = SecurityDepositTransaction::query()
            ->where('invoice_id', $invoiceId)
            ->where('type', SecurityDepositTransaction::TYPE_COLLECTED)
            ->first();
        $this->assertNotNull($transaction);

        $this->assertSame(
            1,
            JournalEntry::query()
                ->where('source_type', JournalEntry::SOURCE_SECURITY_DEPOSIT_COLLECTION)
                ->where('source_id', $transaction->id)
                ->count()
        );
    }

    public function test_security_deposit_collection_journal_is_idempotent(): void
    {
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-DEP-JE-'.uniqid(),
            'type' => Invoice::TYPE_RENT,
            'status' => Invoice::STATUS_CONFIRMED,
            'total' => 300,
            'security_deposit' => 200,
            'deposit_paid_amount' => 0,
            'security_deposit_status' => SecurityDepositStatus::NONE->value,
        ]);

        $transaction = SecurityDepositTransaction::query()->create([
            'invoice_id' => $invoice->id,
            'type' => SecurityDepositTransaction::TYPE_COLLECTED,
            'amount' => 200,
        ]);

        $service = app(JournalEntryPostingService::class);
        $first = $service->postFromSecurityDepositCollection($transaction, $this->user->id);
        $second = $service->postFromSecurityDepositCollection($transaction->refresh(), $this->user->id);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            1,
            JournalEntry::query()
                ->where('source_type', JournalEntry::SOURCE_SECURITY_DEPOSIT_COLLECTION)
                ->where('source_id', $transaction->id)
                ->count()
        );
    }

    public function test_invoice_payment_journal_is_idempotent(): void
    {
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-PAY-JE-'.uniqid(),
            'type' => Invoice::TYPE_SELL,
            'status' => Invoice::STATUS_CONFIRMED,
            'total' => 100,
            'paid_amount' => 0,
            'remaining_amount' => 100,
        ]);

        $payment = InvoicePayment::query()->create([
            'invoice_id' => $invoice->id,
            'amount' => 50,
            'status' => PaymentStatus::PAID->value,
            'payment_type' => InvoicePayment::TYPE_INVOICE_PAYMENT,
            'paid_at' => now(),
        ]);

        $service = app(JournalEntryPostingService::class);
        $first = $service->postFromInvoicePayment($payment, $this->user->id);
        $second = $service->postFromInvoicePayment($payment->refresh(), $this->user->id);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
    }

    private function assertJournalCreditsAccount(JournalEntry $entry, string $accountCode, float $amount): void
    {
        $accountId = Account::query()->where('code', $accountCode)->value('id');
        $credit = (float) JournalEntryLine::query()
            ->where('journal_entry_id', $entry->id)
            ->where('account_id', $accountId)
            ->value('credit');

        $this->assertEqualsWithDelta($amount, $credit, 0.01);
    }

    private function assertJournalDoesNotCreditAccount(JournalEntry $entry, string $accountCode): void
    {
        $accountId = Account::query()->where('code', $accountCode)->value('id');
        $credit = (float) JournalEntryLine::query()
            ->where('journal_entry_id', $entry->id)
            ->where('account_id', $accountId)
            ->sum('credit');

        $this->assertEqualsWithDelta(0.0, $credit, 0.01);
    }

    /**
     * @return array{0: Dress, 1: DressCategory, 2: DressCategory}
     */
    private function createRentableDress(): array
    {
        $category = DressCategory::query()->create(['name' => 'Rent Cat', 'status' => 'active']);
        $subcategory = DressCategory::query()->create([
            'name' => 'Rent Sub',
            'parent_id' => $category->id,
            'status' => 'active',
        ]);
        $dress = Dress::query()->create([
            'dress_category_id' => $category->id,
            'dress_subcategory_id' => $subcategory->id,
            'code' => 'DR-RENT-'.uniqid(),
            'name' => 'Rent Dress',
            'status' => Dress::STATUS_AVAILABLE,
        ]);

        return [$dress, $category, $subcategory];
    }

    private function prepareSqliteDatabases(): void
    {
        $testingPath = storage_path('framework/testing');
        if (! is_dir($testingPath)) {
            mkdir($testingPath, 0777, true);
        }

        $this->centralDatabasePath = $testingPath.'/central-invoice-initial-pay.sqlite';
        $this->tenantDatabasePath = $testingPath.'/tenant-invoice-initial-pay.sqlite';

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
