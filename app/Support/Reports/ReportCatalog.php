<?php

namespace App\Support\Reports;

class ReportCatalog
{
    /**
     * @return list<array{key: string, label: string, label_ar: string, permission: string, group: string}>
     */
    public static function all(): array
    {
        return [
            ['key' => 'overview', 'label' => 'Executive Overview', 'label_ar' => 'نظرة عامة', 'permission' => 'reports.view', 'group' => 'general'],
            ['key' => 'sales', 'label' => 'Sales Report', 'label_ar' => 'تقرير المبيعات', 'permission' => 'reports.sales', 'group' => 'commercial'],
            ['key' => 'sales-daily', 'label' => 'Daily Sales', 'label_ar' => 'المبيعات اليومية', 'permission' => 'reports.sales', 'group' => 'commercial'],
            ['key' => 'sales-products', 'label' => 'Sales by Product', 'label_ar' => 'المبيعات حسب المنتج', 'permission' => 'reports.sales', 'group' => 'commercial'],
            ['key' => 'sales-employees', 'label' => 'Sales by Employee', 'label_ar' => 'المبيعات حسب الموظف', 'permission' => 'reports.sales', 'group' => 'commercial'],
            ['key' => 'rental', 'label' => 'Rental Report', 'label_ar' => 'تقرير الإيجار', 'permission' => 'reports.rental', 'group' => 'commercial'],
            ['key' => 'tailoring', 'label' => 'Tailoring Report', 'label_ar' => 'تقرير التفصيل', 'permission' => 'reports.tailoring', 'group' => 'commercial'],
            ['key' => 'deliveries', 'label' => 'Deliveries Report', 'label_ar' => 'تقرير التسليمات', 'permission' => 'reports.deliveries', 'group' => 'operations'],
            ['key' => 'returns', 'label' => 'Returns Report', 'label_ar' => 'تقرير المرتجعات', 'permission' => 'reports.returns', 'group' => 'operations'],
            ['key' => 'customers', 'label' => 'Customers Report', 'label_ar' => 'تقرير العملاء', 'permission' => 'reports.customers', 'group' => 'crm'],
            ['key' => 'inventory', 'label' => 'Inventory Report', 'label_ar' => 'تقرير المخزون', 'permission' => 'reports.inventory', 'group' => 'inventory'],
            ['key' => 'expenses', 'label' => 'Expenses Report', 'label_ar' => 'تقرير المصروفات', 'permission' => 'reports.expenses', 'group' => 'finance'],
            ['key' => 'cash', 'label' => 'Cash Report', 'label_ar' => 'تقرير الخزينة', 'permission' => 'reports.cash', 'group' => 'finance'],
            ['key' => 'accounting', 'label' => 'Accounting Report', 'label_ar' => 'تقرير المحاسبة', 'permission' => 'reports.accounting', 'group' => 'finance'],
            ['key' => 'payments', 'label' => 'Payments Report', 'label_ar' => 'تقرير المدفوعات', 'permission' => 'reports.payments', 'group' => 'finance'],
            ['key' => 'suppliers', 'label' => 'Suppliers Report', 'label_ar' => 'تقرير الموردين', 'permission' => 'reports.suppliers', 'group' => 'procurement'],
        ];
    }

    /**
     * @return array{key: string, label: string, label_ar: string, permission: string, group: string}|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $report) {
            if ($report['key'] === $key) {
                return $report;
            }
        }

        return null;
    }
}
