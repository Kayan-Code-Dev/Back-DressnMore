<?php

namespace App\Support;

class PlanFeatureCatalog
{
    /**
     * @return list<array{key: string, label: string, group: string, type: string, description: string}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => 'dashboard.enabled', 'label' => 'Dashboard', 'group' => 'core', 'type' => 'boolean', 'description' => 'Overview and KPIs'],
            ['key' => 'customers.enabled', 'label' => 'Customers', 'group' => 'core', 'type' => 'boolean', 'description' => 'Customer management'],
            ['key' => 'categories.enabled', 'label' => 'Categories', 'group' => 'catalog', 'type' => 'boolean', 'description' => 'Dress categories'],
            ['key' => 'subcategories.enabled', 'label' => 'Subcategories', 'group' => 'catalog', 'type' => 'boolean', 'description' => 'Dress subcategories'],
            ['key' => 'dresses.enabled', 'label' => 'Dresses / Inventory', 'group' => 'catalog', 'type' => 'boolean', 'description' => 'Product catalog'],
            ['key' => 'inventory.enabled', 'label' => 'Inventory movements', 'group' => 'catalog', 'type' => 'boolean', 'description' => 'Stock transfers and movements'],
            ['key' => 'branches.enabled', 'label' => 'Branches', 'group' => 'operations', 'type' => 'boolean', 'description' => 'Multi-branch support'],
            ['key' => 'invoices.enabled', 'label' => 'Invoices', 'group' => 'sales', 'type' => 'boolean', 'description' => 'Rent, sale, tailoring invoices'],
            ['key' => 'orders.enabled', 'label' => 'Rental orders', 'group' => 'sales', 'type' => 'boolean', 'description' => 'Rental order management'],
            ['key' => 'payments.enabled', 'label' => 'Payments', 'group' => 'sales', 'type' => 'boolean', 'description' => 'Invoice payments'],
            ['key' => 'deliveries.enabled', 'label' => 'Deliveries', 'group' => 'sales', 'type' => 'boolean', 'description' => 'Rent delivery workflow'],
            ['key' => 'returns.enabled', 'label' => 'Returns', 'group' => 'sales', 'type' => 'boolean', 'description' => 'Rent return workflow'],
            ['key' => 'suppliers.enabled', 'label' => 'Suppliers', 'group' => 'purchasing', 'type' => 'boolean', 'description' => 'Supplier accounts'],
            ['key' => 'purchase_orders.enabled', 'label' => 'Purchase orders', 'group' => 'purchasing', 'type' => 'boolean', 'description' => 'Supplier purchase orders'],
            ['key' => 'supplier_payments.enabled', 'label' => 'Supplier payments', 'group' => 'purchasing', 'type' => 'boolean', 'description' => 'Pay suppliers'],
            ['key' => 'expenses.enabled', 'label' => 'Expenses', 'group' => 'finance', 'type' => 'boolean', 'description' => 'Expense management'],
            ['key' => 'cashboxes.enabled', 'label' => 'Cashboxes', 'group' => 'finance', 'type' => 'boolean', 'description' => 'Cashbox balances'],
            ['key' => 'cash_movements.enabled', 'label' => 'Cash movements', 'group' => 'finance', 'type' => 'boolean', 'description' => 'Manual cash entries'],
            ['key' => 'reports.enabled', 'label' => 'Reports', 'group' => 'analytics', 'type' => 'boolean', 'description' => 'Sales and tailoring reports'],
            ['key' => 'accounting.enabled', 'label' => 'Accounting', 'group' => 'analytics', 'type' => 'boolean', 'description' => 'Accounting summary and ledger'],
            ['key' => 'branches.max', 'label' => 'Max branches', 'group' => 'limits', 'type' => 'integer', 'description' => '0 = unlimited'],
            ['key' => 'users.max', 'label' => 'Max staff users', 'group' => 'limits', 'type' => 'integer', 'description' => '0 = unlimited'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::definitions(), 'key');
    }

    public static function isBooleanKey(string $key): bool
    {
        return str_ends_with($key, '.enabled');
    }

    public static function normalizeValue(string $key, mixed $value): string
    {
        if (self::isBooleanKey($key)) {
            $normalized = strtolower(trim((string) $value));

            return in_array($normalized, ['1', 'true', 'yes', 'enabled', 'on'], true) ? 'true' : 'false';
        }

        $numeric = (int) $value;

        return (string) max(0, $numeric);
    }

    public static function valueType(string $key): string
    {
        return self::isBooleanKey($key) ? 'boolean' : 'integer';
    }

    public static function isEnabledValue(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'enabled'], true);
    }
}
