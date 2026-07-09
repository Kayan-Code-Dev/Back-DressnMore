<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tenant = App\Models\Central\Tenant::where('slug', 'sprint5-test')->first();
config(['database.connections.tenant.database' => $tenant->database_name]);
DB::purge('tenant');
DB::reconnect('tenant');

echo "=== SPRINT 09: FINAL BETA LAUNCH GATE ===\n";
echo "Tenant: {$tenant->database_name}\n\n";

// ===== HELPERS =====
function ok($label, $cond) { echo ($cond ? "PASS" : "FAIL") . " - {$label}\n"; }
function section($s) { echo "\n=== {$s} ===\n"; }

$invSvc = app(App\Services\Tenant\InvoiceService::class);
$saleSvc = app(App\Services\Tenant\SalesService::class);
$rentSvc = app(App\Services\Tenant\RentalService::class);
$paySvc = app(App\Services\Tenant\InvoicePaymentService::class);
$tailorSvc = app(App\Services\Tenant\TailoringOrderService::class);
$hrSvc = app(App\Services\Tenant\EmployeeFinancialService::class);
$empSvc = app(App\Services\Tenant\EmployeeService::class);

$customer = App\Models\Tenant\Customer::on('tenant')->first();
$branch = App\Models\Tenant\Branch::on('tenant')->first();
$owner = App\Models\Tenant\User::on('tenant')->first();
$actorId = $owner ? $owner->id : 1;

// ===== A) TENANT SETTINGS =====
section("A. TENANT SETTINGS");
$settings = App\Models\Tenant\Setting::on('tenant')->first();
$taxRate = $settings ? ($settings->settings['invoice']['tax_rate'] ?? 14) : 14;
$currency = $settings ? ($settings->settings['app']['currency'] ?? 'EGP') : 'EGP';
ok("VAT rate exists", $taxRate > 0);
ok("Currency exists", strlen($currency) > 0);
echo "  VAT={$taxRate}%, Currency={$currency}\n";

// ===== B) SETUP: CREATE CLEAN TEST DATA =====
section("B. TEST DATA SETUP");

// Cashbox
$cashbox = App\Models\Tenant\Cashbox::on('tenant')->find(1);
$cashbox->update(['current_balance' => 50000, 'opening_balance' => 50000]);
$cbStart = (float) $cashbox->current_balance;
echo "Cashbox start: {$cbStart}\n";

// Create test customer
$newCustomer = App\Models\Tenant\Customer::on('tenant')->create([
    'name' => 'Beta Test Customer',
    'phone' => '01099999999',
    'national_id' => '29801099999999',
]);
ok("Customer created", $newCustomer->id > 0);
echo "  Customer #{$newCustomer->id}\n";

// Create test dresses (sale, rental, tailoring)
$cat = App\Models\Tenant\DressCategory::on('tenant')->first();
$subcat = $cat;

$saleDress = App\Models\Tenant\Dress::on('tenant')->create([
    'code' => 'BETA-SALE-01', 'name' => 'Beta Sale Dress',
    'dress_category_id' => $cat->id, 'dress_subcategory_id' => $subcat->id,
    'branch_id' => $branch->id, 'size' => 'M', 'color' => 'Blue',
    'purchase_price' => 1000, 'sale_price' => 3000, 'rental_price' => 500,
    'status' => 'available',
]);
ok("Sale dress created", $saleDress->id > 0);
echo "  #{$saleDress->id} code={$saleDress->code} cost={$saleDress->purchase_price}\n";

$rentDress = App\Models\Tenant\Dress::on('tenant')->create([
    'code' => 'BETA-RENT-01', 'name' => 'Beta Rental Dress',
    'dress_category_id' => $cat->id, 'dress_subcategory_id' => $subcat->id,
    'branch_id' => $branch->id, 'size' => 'L', 'color' => 'Red',
    'purchase_price' => 800, 'sale_price' => 2500, 'rental_price' => 500,
    'status' => 'available',
]);
ok("Rental dress created", $rentDress->id > 0);
echo "  #{$rentDress->id} code={$rentDress->code} cost={$rentDress->purchase_price}\n";

$tailorDress = App\Models\Tenant\Dress::on('tenant')->create([
    'code' => 'BETA-TAILOR-01', 'name' => 'Beta Tailor Dress',
    'dress_category_id' => $cat->id, 'dress_subcategory_id' => $subcat->id,
    'branch_id' => $branch->id, 'size' => 'S', 'color' => 'White',
    'purchase_price' => 600, 'sale_price' => 2000, 'rental_price' => 400,
    'status' => 'available',
]);
ok("Tailoring dress created", $tailorDress->id > 0);
echo "  #{$tailorDress->id} code={$tailorDress->code} cost={$tailorDress->purchase_price}\n";

// ===== C) SALE FLOW =====
section("C. SALE FLOW");
$saleInv = $saleSvc->createSale([
    'customer_id' => $newCustomer->id, 'branch_id' => $branch->id,
    'discount' => 500,
    'items' => [['dress_id' => $saleDress->id, 'description' => $saleDress->name, 'quantity' => 1, 'unit_price' => 3000]],
], $actorId);

$expectedTax = round((3000 - 500) * $taxRate / 100, 2);
$expectedTotal = 3000 - 500 + $expectedTax;
ok("Sale created", $saleInv->id > 0);
ok("Sale tax correct", (float)$saleInv->tax == $expectedTax);
ok("Sale total correct", (float)$saleInv->total == $expectedTotal);
echo "  Invoice #{$saleInv->id}: subtotal={$saleInv->subtotal} discount={$saleInv->discount} tax={$saleInv->tax} total={$saleInv->total}\n";
echo "  Expected: tax={$expectedTax} total={$expectedTotal}\n";

// Verify dress is SOLD
$saleDress->refresh();
ok("Dress marked SOLD", $saleDress->status === 'sold');
echo "  Dress status: {$saleDress->status}\n";

// Try to sell same dress again
try {
    $saleSvc->createSale([
        'customer_id' => $newCustomer->id, 'branch_id' => $branch->id,
        'items' => [['dress_id' => $saleDress->id, 'description' => $saleDress->name, 'quantity' => 1, 'unit_price' => 3000]],
    ], $actorId);
    ok("Re-sell blocked", false);
} catch (Exception $e) {
    ok("Re-sell blocked", true);
    echo "  Blocked: " . substr($e->getMessage(), 0, 80) . "\n";
}

// Pay full amount
$paySvc->recordPaidPayment($saleInv, ['amount' => (float)$saleInv->total, 'method' => 'cash', 'cashbox_id' => $cashbox->id, 'paid_at' => now()], $actorId);
$saleInv->refresh();
ok("Sale fully paid", (float)$saleInv->paid_amount == (float)$saleInv->total);
echo "  Paid={$saleInv->paid_amount} Remaining={$saleInv->remaining_amount}\n";

// Profit check
$profit = (float)$saleInv->total - (float)$saleDress->purchase_price;
ok("Sale profit correct", $profit == $expectedTotal - 1000);
echo "  Profit: revenue={$saleInv->total} cost={$saleDress->purchase_price} profit={$profit}\n";

// Cancel paid sale
$cbBeforeCancel = (float)$cashbox->current_balance;
$invSvc->cancel($saleInv, 'Beta test: cancel paid sale', $actorId);
$saleInv->refresh(); $cashbox->refresh(); $saleDress->refresh();
ok("Sale cancelled", $saleInv->status === 'cancelled');
ok("Dress restored after cancel", $saleDress->status === 'available');
echo "  Status={$saleInv->status} Dress={$saleDress->status}\n";
echo "  Cashbox before={$cbBeforeCancel} after=" . (float)$cashbox->current_balance . "\n";

// Payment after cancel blocked
try {
    $paySvc->recordPaidPayment($saleInv, ['amount' => 100, 'method' => 'cash'], $actorId);
    ok("Payment after cancel blocked", false);
} catch (Exception $e) {
    ok("Payment after cancel blocked", true);
}

echo "\n=== PARTIAL - Save state for next script ===\n";
echo "CUSTOMER={$newCustomer->id}\n";
echo "SALE_DRESS={$saleDress->id}\n";
echo "RENT_DRESS={$rentDress->id}\n";
echo "TAILOR_DRESS={$tailorDress->id}\n";
echo "BRANCH={$branch->id}\n";
echo "ACTOR={$actorId}\n";
echo "CASHBOX={$cashbox->id}\n";
echo "CB_BALANCE=" . (float)$cashbox->current_balance . "\n";
