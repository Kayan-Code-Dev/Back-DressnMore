<?php

use App\Http\Controllers\Tenant\AuthController;
use App\Http\Controllers\Tenant\BranchController;
use App\Http\Controllers\Tenant\CashboxController;
use App\Http\Controllers\Tenant\CashMovementController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\DressCategoryController;
use App\Http\Controllers\Tenant\DressController;
use App\Http\Controllers\Tenant\ExpenseCategoryController;
use App\Http\Controllers\Tenant\ExpenseController;
use App\Http\Controllers\Tenant\HealthController;
use App\Http\Controllers\Tenant\InvoiceController;
use App\Http\Controllers\Tenant\InvoiceDeliveryController;
use App\Http\Controllers\Tenant\LookupController;
use App\Http\Controllers\Tenant\PaymentController;
use App\Http\Controllers\Tenant\PurchaseOrderController;
use App\Http\Controllers\Tenant\SupplierController;
use App\Http\Controllers\Tenant\SupplierPaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant')->group(function (): void {
    Route::get('/health', [HealthController::class, 'index'])
        ->middleware(['identify.tenant', 'check.tenant.subscription', 'set.tenant.database']);

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware(['identify.tenant', 'check.tenant.subscription', 'set.tenant.database']);

    Route::middleware([
        'identify.tenant',
        'check.tenant.subscription',
        'set.tenant.database',
        'auth:sanctum',
    ])->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/lookups', [LookupController::class, 'index']);

        Route::prefix('/customers')->group(function (): void {
            Route::get('/export', [CustomerController::class, 'export'])
                ->middleware('tenant.permission:customers.export');
            Route::get('/', [CustomerController::class, 'index'])
                ->middleware('tenant.permission:customers.view');
            Route::post('/', [CustomerController::class, 'store'])
                ->middleware('tenant.permission:customers.create');
            Route::get('/{customer}', [CustomerController::class, 'show'])
                ->whereNumber('customer')
                ->middleware('tenant.permission:customers.view');
            Route::put('/{customer}', [CustomerController::class, 'update'])
                ->whereNumber('customer')
                ->middleware('tenant.permission:customers.update');
            Route::delete('/{customer}', [CustomerController::class, 'destroy'])
                ->whereNumber('customer')
                ->middleware('tenant.permission:customers.delete');
        });

        Route::prefix('/branches')->group(function (): void {
            Route::get('/export', [BranchController::class, 'export'])
                ->middleware('tenant.permission:branches.export');
            Route::get('/', [BranchController::class, 'index'])
                ->middleware('tenant.permission:branches.view');
            Route::post('/', [BranchController::class, 'store'])
                ->middleware('tenant.permission:branches.create');
            Route::get('/{branch}', [BranchController::class, 'show'])
                ->whereNumber('branch')
                ->middleware('tenant.permission:branches.view');
            Route::put('/{branch}', [BranchController::class, 'update'])
                ->whereNumber('branch')
                ->middleware('tenant.permission:branches.update');
            Route::delete('/{branch}', [BranchController::class, 'destroy'])
                ->whereNumber('branch')
                ->middleware('tenant.permission:branches.delete');
        });

        Route::prefix('/suppliers')->group(function (): void {
            Route::get('/export', [SupplierController::class, 'export'])
                ->middleware('tenant.permission:suppliers.export');
            Route::get('/', [SupplierController::class, 'index'])
                ->middleware('tenant.permission:suppliers.view');
            Route::post('/', [SupplierController::class, 'store'])
                ->middleware('tenant.permission:suppliers.create');
            Route::get('/{supplier}', [SupplierController::class, 'show'])
                ->whereNumber('supplier')
                ->middleware('tenant.permission:suppliers.view');
            Route::put('/{supplier}', [SupplierController::class, 'update'])
                ->whereNumber('supplier')
                ->middleware('tenant.permission:suppliers.update');
            Route::delete('/{supplier}', [SupplierController::class, 'destroy'])
                ->whereNumber('supplier')
                ->middleware('tenant.permission:suppliers.delete');
            Route::get('/{supplier}/payments', [SupplierPaymentController::class, 'indexForSupplier'])
                ->whereNumber('supplier')
                ->middleware('tenant.permission:supplier_payments.view');
            Route::post('/{supplier}/payments', [SupplierPaymentController::class, 'storeForSupplier'])
                ->whereNumber('supplier')
                ->middleware('tenant.permission:supplier_payments.create');
        });

        Route::prefix('/purchase-orders')->group(function (): void {
            Route::get('/export', [PurchaseOrderController::class, 'export'])
                ->middleware('tenant.permission:purchase_orders.export');
            Route::get('/', [PurchaseOrderController::class, 'index'])
                ->middleware('tenant.permission:purchase_orders.view');
            Route::post('/', [PurchaseOrderController::class, 'store'])
                ->middleware('tenant.permission:purchase_orders.create');
            Route::post('/{purchaseOrder}/return', [PurchaseOrderController::class, 'returnOrder'])
                ->whereNumber('purchaseOrder')
                ->middleware('tenant.permission:purchase_orders.return');
            Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
                ->whereNumber('purchaseOrder')
                ->middleware('tenant.permission:purchase_orders.view');
            Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])
                ->whereNumber('purchaseOrder')
                ->middleware('tenant.permission:purchase_orders.update');
            Route::delete('/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])
                ->whereNumber('purchaseOrder')
                ->middleware('tenant.permission:purchase_orders.delete');
            Route::get('/{purchaseOrder}/payments', [SupplierPaymentController::class, 'indexForPurchaseOrder'])
                ->whereNumber('purchaseOrder')
                ->middleware('tenant.permission:supplier_payments.view');
        });

        Route::prefix('/expense-categories')->group(function (): void {
            Route::get('/', [ExpenseCategoryController::class, 'index'])
                ->middleware('tenant.permission:expense_categories.view');
            Route::post('/', [ExpenseCategoryController::class, 'store'])
                ->middleware('tenant.permission:expense_categories.create');
            Route::get('/{expenseCategory}', [ExpenseCategoryController::class, 'show'])
                ->whereNumber('expenseCategory')
                ->middleware('tenant.permission:expense_categories.view');
            Route::put('/{expenseCategory}', [ExpenseCategoryController::class, 'update'])
                ->whereNumber('expenseCategory')
                ->middleware('tenant.permission:expense_categories.update');
            Route::delete('/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])
                ->whereNumber('expenseCategory')
                ->middleware('tenant.permission:expense_categories.delete');
        });

        Route::prefix('/expenses')->group(function (): void {
            Route::get('/summary', [ExpenseController::class, 'summary'])
                ->middleware('tenant.permission:expenses.summary');
            Route::get('/export', [ExpenseController::class, 'export'])
                ->middleware('tenant.permission:expenses.export');
            Route::get('/', [ExpenseController::class, 'index'])
                ->middleware('tenant.permission:expenses.view');
            Route::post('/', [ExpenseController::class, 'store'])
                ->middleware('tenant.permission:expenses.create');
            Route::post('/{expense}/approve', [ExpenseController::class, 'approve'])
                ->whereNumber('expense')
                ->middleware('tenant.permission:expenses.approve');
            Route::post('/{expense}/cancel', [ExpenseController::class, 'cancel'])
                ->whereNumber('expense')
                ->middleware('tenant.permission:expenses.cancel');
            Route::post('/{expense}/pay', [ExpenseController::class, 'pay'])
                ->whereNumber('expense')
                ->middleware('tenant.permission:expenses.pay');
            Route::get('/{expense}', [ExpenseController::class, 'show'])
                ->whereNumber('expense')
                ->middleware('tenant.permission:expenses.view');
            Route::put('/{expense}', [ExpenseController::class, 'update'])
                ->whereNumber('expense')
                ->middleware('tenant.permission:expenses.update');
            Route::delete('/{expense}', [ExpenseController::class, 'destroy'])
                ->whereNumber('expense')
                ->middleware('tenant.permission:expenses.delete');
        });

        Route::prefix('/cash-movements')->group(function (): void {
            Route::get('/', [CashMovementController::class, 'index'])
                ->middleware('tenant.permission:cash_movements.view');
            Route::post('/', [CashMovementController::class, 'store'])
                ->middleware('tenant.permission:cash_movements.create');
        });

        Route::prefix('/cashboxes')->group(function (): void {
            Route::get('/export', [CashboxController::class, 'export'])
                ->middleware('tenant.permission:cashboxes.export');
            Route::get('/daily-summary', [CashboxController::class, 'dailySummary'])
                ->middleware('tenant.permission:cashboxes.view');
            Route::get('/', [CashboxController::class, 'index'])
                ->middleware('tenant.permission:cashboxes.view');
            Route::post('/', [CashboxController::class, 'store'])
                ->middleware('tenant.permission:cashboxes.create');
            Route::get('/{cashbox}', [CashboxController::class, 'show'])
                ->whereNumber('cashbox')
                ->middleware('tenant.permission:cashboxes.view');
            Route::put('/{cashbox}', [CashboxController::class, 'update'])
                ->whereNumber('cashbox')
                ->middleware('tenant.permission:cashboxes.update');
            Route::delete('/{cashbox}', [CashboxController::class, 'destroy'])
                ->whereNumber('cashbox')
                ->middleware('tenant.permission:cashboxes.delete');
            Route::get('/{cashbox}/transactions', [CashboxController::class, 'transactions'])
                ->whereNumber('cashbox')
                ->middleware('tenant.permission:cashboxes.view');
            Route::post('/{cashbox}/recalculate', [CashboxController::class, 'recalculate'])
                ->whereNumber('cashbox')
                ->middleware('tenant.permission:cashboxes.recalculate');
        });

        Route::prefix('/payments')->group(function (): void {
            Route::get('/export', [PaymentController::class, 'export'])
                ->middleware('tenant.permission:payments.export');
            Route::get('/', [PaymentController::class, 'index'])
                ->middleware('tenant.permission:payments.view');
            Route::get('/{payment}', [PaymentController::class, 'show'])
                ->whereNumber('payment')
                ->middleware('tenant.permission:payments.view');
            Route::post('/{payment}/pay', [PaymentController::class, 'pay'])
                ->whereNumber('payment')
                ->middleware('tenant.permission:payments.pay');
            Route::post('/{payment}/cancel', [PaymentController::class, 'cancel'])
                ->whereNumber('payment')
                ->middleware('tenant.permission:payments.cancel');
        });

        Route::prefix('/dress-categories')->group(function (): void {
            Route::get('/', [DressCategoryController::class, 'index'])
                ->middleware('tenant.permission:dress_categories.view');
            Route::post('/', [DressCategoryController::class, 'store'])
                ->middleware('tenant.permission:dress_categories.create');
            Route::get('/{dressCategory}', [DressCategoryController::class, 'show'])
                ->whereNumber('dressCategory')
                ->middleware('tenant.permission:dress_categories.view');
            Route::put('/{dressCategory}', [DressCategoryController::class, 'update'])
                ->whereNumber('dressCategory')
                ->middleware('tenant.permission:dress_categories.update');
            Route::delete('/{dressCategory}', [DressCategoryController::class, 'destroy'])
                ->whereNumber('dressCategory')
                ->middleware('tenant.permission:dress_categories.delete');
        });

        Route::prefix('/dresses')->group(function (): void {
            Route::get('/export', [DressController::class, 'export'])
                ->middleware('tenant.permission:dresses.export');
            Route::get('/available-for-date', [DressController::class, 'availableForDate'])
                ->middleware('tenant.permission:dresses.view');
            Route::get('/', [DressController::class, 'index'])
                ->middleware('tenant.permission:dresses.view');
            Route::post('/', [DressController::class, 'store'])
                ->middleware('tenant.permission:dresses.create');
            Route::get('/{dress}/order-history', [DressController::class, 'orderHistory'])
                ->whereNumber('dress')
                ->middleware('tenant.permission:dresses.view');
            Route::get('/{dress}/unavailable-days', [DressController::class, 'unavailableDays'])
                ->whereNumber('dress')
                ->middleware('tenant.permission:dresses.view');
            Route::get('/{dress}', [DressController::class, 'show'])
                ->whereNumber('dress')
                ->middleware('tenant.permission:dresses.view');
            Route::put('/{dress}', [DressController::class, 'update'])
                ->whereNumber('dress')
                ->middleware('tenant.permission:dresses.update');
            Route::delete('/{dress}', [DressController::class, 'destroy'])
                ->whereNumber('dress')
                ->middleware('tenant.permission:dresses.delete');
            Route::get('/{dress}/inventory-movements', [DressController::class, 'inventoryMovements'])
                ->whereNumber('dress')
                ->middleware('tenant.permission:inventory.view');
        });

        Route::prefix('/invoices')->group(function (): void {
            Route::get('/export', [InvoiceController::class, 'export'])
                ->middleware('tenant.permission:invoices.export');
            Route::get('/', [InvoiceController::class, 'index'])
                ->middleware('tenant.permission:invoices.view');
            Route::post('/', [InvoiceController::class, 'store'])
                ->middleware('tenant.permission:invoices.create');
            Route::post('/{invoice}/cancel', [InvoiceController::class, 'cancel'])
                ->whereNumber('invoice')
                ->middleware('tenant.permission:invoices.cancel');
            Route::get('/{invoice}', [InvoiceController::class, 'show'])
                ->whereNumber('invoice')
                ->middleware('tenant.permission:invoices.view');
            Route::put('/{invoice}', [InvoiceController::class, 'update'])
                ->whereNumber('invoice')
                ->middleware('tenant.permission:invoices.update');
            Route::delete('/{invoice}', [InvoiceController::class, 'destroy'])
                ->whereNumber('invoice')
                ->middleware('tenant.permission:invoices.delete');

            Route::get('/{invoice}/payments', [InvoiceController::class, 'payments'])
                ->whereNumber('invoice')
                ->middleware('tenant.permission:invoice_payments.view');
            Route::post('/{invoice}/payments', [InvoiceController::class, 'addPayment'])
                ->whereNumber('invoice')
                ->middleware('tenant.permission:invoice_payments.create');

            Route::post('/{invoice}/deliver', [InvoiceDeliveryController::class, 'deliver'])
                ->whereNumber('invoice')
                ->middleware('tenant.permission:invoice_delivery.deliver');
            Route::post('/{invoice}/return', [InvoiceDeliveryController::class, 'returnInvoice'])
                ->whereNumber('invoice')
                ->middleware('tenant.permission:invoice_delivery.return');
            Route::get('/{invoice}/delivery-records', [InvoiceDeliveryController::class, 'deliveryRecords'])
                ->whereNumber('invoice')
                ->middleware('tenant.permission:invoice_delivery.view');

            Route::post('/{invoice}/security-deposit/deductions', [InvoiceDeliveryController::class, 'addSecurityDepositDeduction'])
                ->whereNumber('invoice')
                ->middleware('tenant.permission:security_deposit.deduct');
            Route::get('/{invoice}/security-deposit/transactions', [InvoiceDeliveryController::class, 'securityDepositTransactions'])
                ->whereNumber('invoice')
                ->middleware('tenant.permission:security_deposit.view');
        });
    });
});
