<?php

namespace App\Services\Tenant;

use App\Enums\SecurityDepositStatus;
use App\Models\Central\Tenant as CentralTenant;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Cashbox;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Customer;
use App\Models\Tenant\DeliveryRecord;
use App\Models\Tenant\Dress;
use App\Models\Tenant\DressCategory;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
use App\Models\Tenant\InventoryMovement;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoicePayment;
use App\Models\Tenant\Permission;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Role;
use App\Models\Tenant\SecurityDepositTransaction;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierPayment;
use App\Models\Tenant\User;
use Carbon\CarbonImmutable;

class TenantDemoSeedService
{
    private const DEMO_TAG = 'demo_seed';

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly InvoiceService $invoiceService,
        private readonly InvoicePaymentService $invoicePaymentService,
        private readonly InvoiceDeliveryService $invoiceDeliveryService,
        private readonly SecurityDepositService $securityDepositService,
        private readonly ExpenseService $expenseService,
        private readonly CashMovementService $cashMovementService,
        private readonly CashboxService $cashboxService,
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly SupplierPaymentService $supplierPaymentService,
        private readonly SupplierService $supplierService,
    ) {}

    /**
     * @return array<string, bool|int|string>
     */
    public function seed(CentralTenant $tenant): array
    {
        $demoKey = $this->demoKey($tenant);
        $prefix = "DEMO-{$demoKey}";

        [$ownerUser, $managerUser] = $this->seedUsersAndRoles($tenant, $demoKey);
        $actorId = (int) $ownerUser->id;

        $hqBranch = Branch::query()->firstOrCreate(
            ['branch_code' => "{$prefix}-BR-HQ"],
            [
                'name' => 'Demo Main Branch',
                'code' => "{$prefix}-HQ",
                'phone' => '01000000001',
                'address' => 'Demo Main Branch Address',
                'street' => 'Demo Street',
                'building' => '1',
                'city_id' => 1,
                'currency' => 'EGP',
                'currency_id' => 1,
                'vat_enabled' => true,
                'vat_type' => 'percentage',
                'vat_value' => 14,
                'inventory_name' => 'Demo Main Inventory',
                'notes' => self::DEMO_TAG,
                'status' => Branch::STATUS_ACTIVE,
            ]
        );

        Branch::query()->firstOrCreate(
            ['branch_code' => "{$prefix}-BR-ALEX"],
            [
                'name' => 'Demo Secondary Branch',
                'code' => "{$prefix}-ALEX",
                'phone' => '01000000002',
                'address' => 'Demo Secondary Branch Address',
                'street' => 'Demo Avenue',
                'building' => '2',
                'city_id' => 2,
                'currency' => 'EGP',
                'currency_id' => 1,
                'vat_enabled' => false,
                'vat_type' => null,
                'vat_value' => null,
                'inventory_name' => 'Demo Secondary Inventory',
                'notes' => self::DEMO_TAG,
                'status' => Branch::STATUS_ACTIVE,
            ]
        );

        $bridalCategory = DressCategory::query()->firstOrCreate(
            ['slug' => "{$prefix}-CAT-BRIDAL"],
            [
                'name' => 'Demo Bridal',
                'description' => 'Demo bridal category',
                'status' => 'active',
            ]
        );
        $eveningCategory = DressCategory::query()->firstOrCreate(
            ['slug' => "{$prefix}-CAT-EVENING"],
            [
                'name' => 'Demo Evening',
                'description' => 'Demo evening category',
                'status' => 'active',
            ]
        );
        $bridalSubcategory = DressCategory::query()->firstOrCreate(
            ['slug' => "{$prefix}-SUB-BRIDAL-PRINCESS"],
            [
                'parent_id' => $bridalCategory->id,
                'name' => 'Demo Princess',
                'description' => 'Demo bridal princess subcategory',
                'status' => 'active',
            ]
        );
        $eveningSubcategory = DressCategory::query()->firstOrCreate(
            ['slug' => "{$prefix}-SUB-EVENING-SHEATH"],
            [
                'parent_id' => $eveningCategory->id,
                'name' => 'Demo Sheath',
                'description' => 'Demo evening sheath subcategory',
                'status' => 'active',
            ]
        );

        $rentDress = Dress::query()->firstOrCreate(
            ['code' => "{$prefix}-DR-RENT-001"],
            [
                'name' => 'Demo Rent Dress',
                'dress_category_id' => $bridalCategory->id,
                'dress_subcategory_id' => $bridalSubcategory->id,
                'branch_id' => $hqBranch->id,
                'status' => Dress::STATUS_AVAILABLE,
                'size' => 'M',
                'color' => 'Ivory',
                'rental_price' => 1800,
                'sale_price' => 8500,
                'measurements' => [
                    'length' => 160,
                    'neck' => 36,
                ],
                'notes' => self::DEMO_TAG,
            ]
        );
        $this->ensureDressCreatedMovement($rentDress, $actorId);

        $sellDress = Dress::query()->firstOrCreate(
            ['code' => "{$prefix}-DR-SELL-001"],
            [
                'name' => 'Demo Sell Dress',
                'dress_category_id' => $eveningCategory->id,
                'dress_subcategory_id' => $eveningSubcategory->id,
                'branch_id' => $hqBranch->id,
                'status' => Dress::STATUS_AVAILABLE,
                'size' => 'L',
                'color' => 'Red',
                'rental_price' => 950,
                'sale_price' => 6400,
                'notes' => self::DEMO_TAG,
            ]
        );
        $this->ensureDressCreatedMovement($sellDress, $actorId);

        $tailoringDress = Dress::query()->firstOrCreate(
            ['code' => "{$prefix}-DR-TAILOR-001"],
            [
                'name' => 'Demo Tailoring Dress',
                'dress_category_id' => $bridalCategory->id,
                'dress_subcategory_id' => $bridalSubcategory->id,
                'branch_id' => $hqBranch->id,
                'status' => Dress::STATUS_AVAILABLE,
                'size' => 'S',
                'color' => 'White',
                'rental_price' => 0,
                'sale_price' => 0,
                'notes' => self::DEMO_TAG,
            ]
        );
        $this->ensureDressCreatedMovement($tailoringDress, $actorId);

        $customerOne = Customer::query()->firstOrCreate(
            ['national_id' => "{$prefix}-CUST-001"],
            [
                'name' => 'Demo Customer One',
                'date_of_birth' => '1995-01-10',
                'phone' => '01000001001',
                'phone2' => '01000001002',
                'whatsapp' => '01000001003',
                'email' => "demo.customer.one+{$tenant->slug}@dressnmore.test",
                'address' => 'Demo Address One',
                'city_id' => 1,
                'source' => 'instagram',
                'status' => 'active',
                'notes' => self::DEMO_TAG,
            ]
        );
        Customer::query()->firstOrCreate(
            ['national_id' => "{$prefix}-CUST-002"],
            [
                'name' => 'Demo Customer Two',
                'date_of_birth' => '1993-07-02',
                'phone' => '01000002001',
                'phone2' => '01000002002',
                'whatsapp' => '01000002003',
                'email' => "demo.customer.two+{$tenant->slug}@dressnmore.test",
                'address' => 'Demo Address Two',
                'city_id' => 2,
                'source' => 'referral',
                'status' => 'active',
                'notes' => self::DEMO_TAG,
            ]
        );
        $customerThree = Customer::query()->firstOrCreate(
            ['national_id' => "{$prefix}-CUST-003"],
            [
                'name' => 'Demo Customer Tailoring',
                'date_of_birth' => '1998-04-19',
                'phone' => '01000003001',
                'phone2' => '01000003002',
                'whatsapp' => '01000003003',
                'email' => "demo.customer.three+{$tenant->slug}@dressnmore.test",
                'address' => 'Demo Address Three',
                'city_id' => 3,
                'source' => 'walk_in',
                'status' => 'active',
                'notes' => self::DEMO_TAG,
            ]
        );

        $mainCashbox = Cashbox::query()->firstOrCreate(
            ['name' => "{$prefix}-CASHBOX-MAIN"],
            [
                'branch_id' => $hqBranch->id,
                'initial_balance' => 5000,
                'current_balance' => 5000,
                'description' => 'Demo main cashbox',
                'is_active' => true,
            ]
        );

        $operationsCategory = ExpenseCategory::query()->firstOrCreate(
            ['slug' => "{$prefix}-EXP-OPS"],
            [
                'name' => 'Demo Operations',
                'description' => 'Demo operations expenses',
                'status' => ExpenseCategory::STATUS_ACTIVE,
            ]
        );
        $maintenanceCategory = ExpenseCategory::query()->firstOrCreate(
            ['slug' => "{$prefix}-EXP-MAINT"],
            [
                'name' => 'Demo Maintenance',
                'description' => 'Demo maintenance expenses',
                'status' => ExpenseCategory::STATUS_ACTIVE,
            ]
        );

        $rentInvoice = $this->ensureInvoice(
            invoiceNumber: "{$prefix}-INV-RENT-001",
            defaults: [
                'customer_id' => $customerOne->id,
                'branch_id' => $hqBranch->id,
                'type' => Invoice::TYPE_RENT,
                'status' => Invoice::STATUS_CONFIRMED,
                'rent_start_date' => CarbonImmutable::now()->subDays(8)->toDateString(),
                'rent_end_date' => CarbonImmutable::now()->subDays(3)->toDateString(),
                'security_deposit' => 300,
                'security_deposit_status' => SecurityDepositStatus::NONE->value,
                'days_of_rent' => 5,
                'notes' => self::DEMO_TAG,
                'order_notes' => 'Demo rent order',
                'created_by' => $actorId,
            ],
            dressId: (int) $rentDress->id,
            itemDescription: 'Demo rent invoice item',
            quantity: 1,
            unitPrice: 1800,
            itemType: null,
        );

        $sellInvoice = $this->ensureInvoice(
            invoiceNumber: "{$prefix}-INV-SELL-001",
            defaults: [
                'customer_id' => $customerOne->id,
                'branch_id' => $hqBranch->id,
                'type' => Invoice::TYPE_SELL,
                'status' => Invoice::STATUS_CONFIRMED,
                'delivery_date' => CarbonImmutable::now()->subDays(1)->toDateString(),
                'notes' => self::DEMO_TAG,
                'order_notes' => 'Demo sell order',
                'created_by' => $actorId,
            ],
            dressId: (int) $sellDress->id,
            itemDescription: 'Demo sell invoice item',
            quantity: 1,
            unitPrice: 6400,
            itemType: null,
        );

        $tailoringInvoice = $this->ensureInvoice(
            invoiceNumber: "{$prefix}-INV-TAILOR-001",
            defaults: [
                'customer_id' => $customerThree->id,
                'branch_id' => $hqBranch->id,
                'type' => Invoice::TYPE_TAILORING,
                'status' => Invoice::STATUS_CONFIRMED,
                'tailoring_due_date' => CarbonImmutable::now()->addDays(7)->toDateString(),
                'visit_datetime' => CarbonImmutable::now()->subDays(2)->toDateTimeString(),
                'occasion_datetime' => CarbonImmutable::now()->addDays(10)->toDateTimeString(),
                'notes' => self::DEMO_TAG,
                'order_notes' => 'Demo tailoring order',
                'created_by' => $actorId,
            ],
            dressId: (int) $tailoringDress->id,
            itemDescription: 'Demo tailoring service',
            quantity: 1,
            unitPrice: 2300,
            itemType: 'tailoring_service',
        );

        $this->ensureInvoicePayment(
            invoice: $rentInvoice,
            reference: "{$prefix}-PAY-RENT-001",
            amount: 900,
            method: 'cash',
            actorId: $actorId,
        );
        $this->ensureInvoicePayment(
            invoice: $sellInvoice,
            reference: "{$prefix}-PAY-SELL-001",
            amount: 6400,
            method: 'instapay',
            actorId: $actorId,
        );

        $rentInvoice = $this->ensureInvoiceDelivered(
            invoice: $rentInvoice->refresh(),
            actorId: $actorId,
            receiverName: 'Demo Receiver Rent',
            receiverPhone: '01011112222',
        );
        $this->ensureRentInvoiceReturned($rentInvoice->refresh(), $actorId);
        $this->ensureSecurityDepositDeduction($rentInvoice->refresh(), $actorId, "{$prefix}-SEC-DED-001");

        $this->ensureInvoiceDelivered(
            invoice: $sellInvoice->refresh(),
            actorId: $actorId,
            receiverName: 'Demo Receiver Sell',
            receiverPhone: '01033334444',
        );
        $this->ensureInvoiceDelivered(
            invoice: $tailoringInvoice->refresh(),
            actorId: $actorId,
            receiverName: 'Demo Receiver Tailoring',
            receiverPhone: '01055556666',
        );

        Expense::query()->firstOrCreate(
            ['reference_number' => "{$prefix}-EXP-PENDING-001"],
            [
                'expense_category_id' => $operationsCategory->id,
                'branch_id' => $hqBranch->id,
                'cashbox_id' => $mainCashbox->id,
                'amount' => 250,
                'status' => Expense::STATUS_PENDING,
                'method' => 'cash',
                'vendor' => 'Demo Vendor Pending',
                'reference' => "{$prefix}-EXP-PENDING-REF",
                'expense_date' => CarbonImmutable::now()->toDateString(),
                'description' => 'Demo pending expense',
                'notes' => self::DEMO_TAG,
                'created_by' => $actorId,
            ]
        );

        $workflowExpense = Expense::query()->firstOrCreate(
            ['reference_number' => "{$prefix}-EXP-WORKFLOW-001"],
            [
                'expense_category_id' => $maintenanceCategory->id,
                'branch_id' => $hqBranch->id,
                'cashbox_id' => $mainCashbox->id,
                'amount' => 420,
                'status' => Expense::STATUS_PENDING,
                'method' => 'cash',
                'vendor' => 'Demo Vendor Workflow',
                'reference' => "{$prefix}-EXP-WORKFLOW-REF",
                'expense_date' => CarbonImmutable::now()->subDay()->toDateString(),
                'description' => 'Demo workflow expense',
                'notes' => self::DEMO_TAG,
                'created_by' => $actorId,
            ]
        );

        if ($workflowExpense->status === Expense::STATUS_PENDING) {
            $workflowExpense = $this->expenseService->approve($workflowExpense, $actorId);
        } else {
            $workflowExpense = $workflowExpense->refresh();
        }
        if ($workflowExpense->status === Expense::STATUS_APPROVED) {
            $this->expenseService->pay($workflowExpense, [
                'cashbox_id' => $mainCashbox->id,
                'method' => 'cash',
                'notes' => self::DEMO_TAG.'::expense_payment',
                'paid_at' => CarbonImmutable::now()->toDateTimeString(),
            ], $actorId);
        }

        $manualMovementReference = "{$prefix}-MANUAL-OPENING";
        $manualMovement = CashMovement::withTrashed()
            ->where('reference_type', self::DEMO_TAG)
            ->where('reference', $manualMovementReference)
            ->first();
        if (! $manualMovement instanceof CashMovement) {
            $this->cashMovementService->createManual([
                'type' => CashMovement::TYPE_MANUAL_ADJUSTMENT,
                'direction' => CashMovement::DIRECTION_IN,
                'amount' => 5000,
                'method' => 'cash',
                'cashbox_id' => $mainCashbox->id,
                'reference_type' => self::DEMO_TAG,
                'reference' => $manualMovementReference,
                'movement_date' => CarbonImmutable::now()->subDays(5)->toDateTimeString(),
                'description' => 'Demo opening balance',
                'notes' => self::DEMO_TAG,
            ], $actorId);
        } else {
            if ($manualMovement->trashed()) {
                $manualMovement->restore();
            }
            if ($manualMovement->is_reversed) {
                $manualMovement->is_reversed = false;
                $manualMovement->save();
            }
        }

        $supplier = Supplier::query()->firstOrCreate(
            ['code' => "{$prefix}-SUP-001"],
            [
                'name' => 'Demo Supplier One',
                'phone' => '01077778888',
                'whatsapp' => '01077778889',
                'email' => "demo.supplier.one+{$tenant->slug}@dressnmore.test",
                'address' => 'Demo Supplier Address',
                'tax_number' => "{$prefix}-TAX-001",
                'opening_balance' => 0,
                'current_balance' => 0,
                'notes' => self::DEMO_TAG,
                'status' => Supplier::STATUS_ACTIVE,
            ]
        );

        $purchaseOrder = PurchaseOrder::query()->firstOrCreate(
            ['purchase_order_number' => "{$prefix}-PO-001"],
            [
                'supplier_id' => $supplier->id,
                'branch_id' => $hqBranch->id,
                'category_id' => $eveningCategory->id,
                'subcategory_id' => $eveningSubcategory->id,
                'status' => PurchaseOrder::STATUS_CONFIRMED,
                'type' => 'fabric',
                'is_returned' => false,
                'subtotal' => 0,
                'discount' => 50,
                'tax' => 35,
                'total' => 0,
                'paid_amount' => 0,
                'remaining_amount' => 0,
                'order_date' => CarbonImmutable::now()->subDays(4)->toDateString(),
                'notes' => self::DEMO_TAG,
                'created_by' => $actorId,
            ]
        );
        if (! $purchaseOrder->items()->exists()) {
            $purchaseOrder->items()->createMany([
                [
                    'item_name' => 'Demo Satin Fabric',
                    'description' => 'Premium satin fabric roll',
                    'quantity' => 10,
                    'unit_price' => 120,
                    'total' => 1200,
                ],
                [
                    'item_name' => 'Demo Embroidery Set',
                    'description' => 'Embroidery accessories',
                    'quantity' => 3,
                    'unit_price' => 220,
                    'total' => 660,
                ],
            ]);
        }

        $purchaseOrder = $purchaseOrder->refresh();
        $purchaseOrder->subtotal = round((float) $purchaseOrder->items()->sum('total'), 2);
        $purchaseOrder->total = round(max(0, (float) $purchaseOrder->subtotal - (float) $purchaseOrder->discount + (float) $purchaseOrder->tax), 2);
        if ($purchaseOrder->status === PurchaseOrder::STATUS_DRAFT) {
            $purchaseOrder->status = PurchaseOrder::STATUS_CONFIRMED;
        }
        $purchaseOrder->save();
        $purchaseOrder = $this->purchaseOrderService->syncFinancials($purchaseOrder->refresh(), (string) $purchaseOrder->status);

        $supplierPaymentReference = "{$prefix}-SUPPAY-001";
        if (! SupplierPayment::query()->where('reference', $supplierPaymentReference)->exists()) {
            $this->supplierPaymentService->addPayment($supplier->refresh(), [
                'purchase_order_id' => $purchaseOrder->id,
                'amount' => 700,
                'method' => 'cash',
                'reference' => $supplierPaymentReference,
                'paid_at' => CarbonImmutable::now()->subDays(2)->toDateTimeString(),
                'notes' => self::DEMO_TAG,
            ], $actorId);
        }

        $this->supplierService->recalculateCurrentBalance($supplier->refresh());
        $this->cashboxService->recalculate($mainCashbox->refresh());

        return [
            'tenant_slug' => $tenant->slug,
            'idempotent' => true,
            'owner_email' => $ownerUser->email,
            'manager_email' => $managerUser->email,
            'roles_seeded' => Role::query()->whereIn('slug', ['owner', 'demo-manager'])->count(),
            'users_seeded' => User::query()
                ->whereIn('email', [$ownerUser->email, $managerUser->email])
                ->count(),
            'customers_seeded' => Customer::query()
                ->where('national_id', 'like', "{$prefix}-CUST-%")
                ->count(),
            'branches_seeded' => Branch::query()
                ->where('branch_code', 'like', "{$prefix}-BR-%")
                ->count(),
            'categories_seeded' => DressCategory::query()
                ->where('slug', 'like', "{$prefix}-%")
                ->count(),
            'dresses_seeded' => Dress::query()
                ->where('code', 'like', "{$prefix}-DR-%")
                ->count(),
            'invoices_seeded' => Invoice::query()
                ->where('invoice_number', 'like', "{$prefix}-INV-%")
                ->count(),
            'invoice_payments_seeded' => InvoicePayment::query()
                ->where('reference', 'like', "{$prefix}-PAY-%")
                ->count(),
            'delivery_records_seeded' => DeliveryRecord::query()
                ->whereHas('invoice', fn ($query) => $query->where('invoice_number', 'like', "{$prefix}-INV-%"))
                ->count(),
            'security_deductions_seeded' => SecurityDepositTransaction::query()
                ->where('notes', self::DEMO_TAG.'::security_deduction')
                ->count(),
            'expense_categories_seeded' => ExpenseCategory::query()
                ->where('slug', 'like', "{$prefix}-EXP-%")
                ->count(),
            'expenses_seeded' => Expense::query()
                ->where('reference_number', 'like', "{$prefix}-EXP-%")
                ->count(),
            'cashboxes_seeded' => Cashbox::query()
                ->where('name', 'like', "{$prefix}-CASHBOX-%")
                ->count(),
            'cash_movements_seeded' => CashMovement::query()
                ->where(function ($query) use ($prefix): void {
                    $query->where('reference', 'like', "{$prefix}-%")
                        ->orWhere('notes', 'like', self::DEMO_TAG.'%');
                })
                ->count(),
            'suppliers_seeded' => Supplier::query()
                ->where('code', 'like', "{$prefix}-SUP-%")
                ->count(),
            'purchase_orders_seeded' => PurchaseOrder::query()
                ->where('purchase_order_number', 'like', "{$prefix}-PO-%")
                ->count(),
            'supplier_payments_seeded' => SupplierPayment::query()
                ->where('reference', 'like', "{$prefix}-SUPPAY-%")
                ->count(),
        ];
    }

    /**
     * @return array{0:User,1:User}
     */
    private function seedUsersAndRoles(CentralTenant $tenant, string $demoKey): array
    {
        $ownerRole = Role::query()->firstOrCreate(
            ['slug' => 'owner'],
            ['name' => 'Owner']
        );
        $allPermissionIds = Permission::query()->pluck('id')->all();
        if ($allPermissionIds !== []) {
            $ownerRole->permissions()->sync($allPermissionIds);
        }

        $ownerUser = User::query()->updateOrCreate(
            ['email' => "demo.owner+{$tenant->slug}@dressnmore.test"],
            [
                'name' => "Demo Owner {$demoKey}",
                'password' => 'password',
                'phone' => '01099990001',
                'status' => 'active',
            ]
        );
        $ownerUser->roles()->syncWithoutDetaching([$ownerRole->id]);

        $managerRole = Role::query()->firstOrCreate(
            ['slug' => 'demo-manager'],
            ['name' => 'Demo Manager']
        );
        $managerPermissionIds = Permission::query()
            ->whereIn('key', [
                'customers.view',
                'customers.create',
                'branches.view',
                'dresses.view',
                'invoices.view',
                'invoice_payments.view',
                'expenses.view',
                'cashboxes.view',
                'suppliers.view',
                'purchase_orders.view',
            ])
            ->pluck('id')
            ->all();
        if ($managerPermissionIds !== []) {
            $managerRole->permissions()->sync($managerPermissionIds);
        }

        $managerUser = User::query()->updateOrCreate(
            ['email' => "demo.manager+{$tenant->slug}@dressnmore.test"],
            [
                'name' => "Demo Manager {$demoKey}",
                'password' => 'password',
                'phone' => '01099990002',
                'status' => 'active',
            ]
        );
        $managerUser->roles()->syncWithoutDetaching([$managerRole->id]);

        return [$ownerUser, $managerUser];
    }

    private function ensureDressCreatedMovement(Dress $dress, ?int $actorId = null): void
    {
        $exists = InventoryMovement::query()
            ->where('dress_id', $dress->id)
            ->where('type', InventoryMovement::TYPE_CREATED)
            ->exists();

        if ($exists) {
            return;
        }

        $this->inventoryService->recordMovement(
            dress: $dress,
            type: InventoryMovement::TYPE_CREATED,
            reason: 'Demo seed dress created',
            notes: self::DEMO_TAG,
            createdBy: $actorId,
        );
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    private function ensureInvoice(
        string $invoiceNumber,
        array $defaults,
        ?int $dressId,
        string $itemDescription,
        int $quantity,
        float $unitPrice,
        ?string $itemType = null,
    ): Invoice {
        $invoice = Invoice::query()->firstOrCreate(
            ['invoice_number' => $invoiceNumber],
            array_merge([
                'subtotal' => 0,
                'discount' => 0,
                'discount_type' => 'fixed',
                'discount_value' => 0,
                'tax' => 0,
                'total' => 0,
                'paid_amount' => 0,
                'remaining_amount' => 0,
            ], $defaults)
        );

        $itemQuery = $invoice->items()->where('description', $itemDescription);
        if ($dressId !== null) {
            $itemQuery->where('dress_id', $dressId);
        } else {
            $itemQuery->whereNull('dress_id');
        }

        if (! $itemQuery->exists()) {
            $invoice->items()->create([
                'dress_id' => $dressId,
                'item_type' => $itemType,
                'description' => $itemDescription,
                'quantity' => $quantity,
                'unit_price' => round($unitPrice, 2),
                'total' => round($unitPrice * $quantity, 2),
            ]);
        }

        $subtotal = round((float) $invoice->items()->sum('total'), 2);
        $discount = round((float) $invoice->discount, 2);
        $tax = round((float) $invoice->tax, 2);
        $invoice->subtotal = $subtotal;
        $invoice->total = round(max(0, $subtotal - $discount + $tax), 2);
        if ($invoice->security_deposit !== null && $invoice->security_deposit_status === null) {
            $invoice->security_deposit_status = SecurityDepositStatus::NONE->value;
        }
        $invoice->save();

        return $this->invoiceService->refreshFinancials($invoice->refresh(), (string) $invoice->status);
    }

    private function ensureInvoicePayment(
        Invoice $invoice,
        string $reference,
        float $amount,
        ?string $method,
        ?int $actorId = null,
    ): void {
        if (InvoicePayment::query()->where('reference', $reference)->exists()) {
            return;
        }

        $this->invoicePaymentService->addPayment($invoice->refresh(), [
            'amount' => round($amount, 2),
            'method' => $method,
            'reference' => $reference,
            'paid_at' => CarbonImmutable::now()->subDay()->toDateTimeString(),
            'notes' => self::DEMO_TAG,
        ], $actorId);
    }

    private function ensureInvoiceDelivered(
        Invoice $invoice,
        ?int $actorId,
        string $receiverName,
        string $receiverPhone,
    ): Invoice {
        if ($invoice->deliveryRecords()->where('type', DeliveryRecord::TYPE_DELIVERED)->exists()) {
            return $invoice->refresh();
        }

        if (in_array($invoice->status, [Invoice::STATUS_DELIVERED, Invoice::STATUS_RETURNED], true)) {
            return $invoice->refresh();
        }

        if (! in_array($invoice->status, [Invoice::STATUS_CONFIRMED, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_PAID], true)) {
            $invoice->status = Invoice::STATUS_CONFIRMED;
            $invoice->save();
        }

        return $this->invoiceDeliveryService->deliver($invoice->refresh(), [
            'delivered_at' => CarbonImmutable::now()->subDays(2)->toDateTimeString(),
            'receiver_name' => $receiverName,
            'receiver_phone' => $receiverPhone,
            'notes' => self::DEMO_TAG.'::delivery',
        ], $actorId);
    }

    private function ensureRentInvoiceReturned(Invoice $invoice, ?int $actorId): void
    {
        if ($invoice->type !== Invoice::TYPE_RENT) {
            return;
        }

        if ($invoice->deliveryRecords()->where('type', DeliveryRecord::TYPE_RETURNED)->exists()) {
            return;
        }

        if ($invoice->status !== Invoice::STATUS_DELIVERED) {
            $invoice = $this->ensureInvoiceDelivered(
                invoice: $invoice->refresh(),
                actorId: $actorId,
                receiverName: 'Demo Return Receiver',
                receiverPhone: '01010101010',
            );
        }

        $this->invoiceDeliveryService->returnRentInvoice($invoice->refresh(), [
            'returned_at' => CarbonImmutable::now()->subDay()->toDateTimeString(),
            'dress_status_after_return' => Dress::STATUS_AVAILABLE,
            'notes' => self::DEMO_TAG.'::return',
        ], $actorId);
    }

    private function ensureSecurityDepositDeduction(Invoice $invoice, ?int $actorId, string $reference): void
    {
        if ($invoice->type !== Invoice::TYPE_RENT) {
            return;
        }

        $note = self::DEMO_TAG.'::security_deduction';
        if (SecurityDepositTransaction::query()
            ->where('invoice_id', $invoice->id)
            ->where('notes', $note)
            ->exists()) {
            return;
        }

        if ((float) ($invoice->security_deposit ?? 0) <= 0) {
            $invoice->security_deposit = 300;
            $invoice->security_deposit_status = SecurityDepositStatus::NONE->value;
            $invoice->save();
        }

        $this->securityDepositService->addDeduction($invoice->refresh(), [
            'amount' => 40,
            'reason' => "Late return fee {$reference}",
            'notes' => $note,
        ], $actorId);
    }

    private function demoKey(CentralTenant $tenant): string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $tenant->slug));

        return $normalized !== '' ? $normalized : 'DEMO';
    }
}
