<?php

namespace App\Support\Reports;

class ReportTabularizer
{
    /**
     * @return array{title: string, headers: list<string>, rows: list<list<string|int|float|null>>}
     */
    public static function fromReport(string $key, array $data): array
    {
        return match ($key) {
            'sales-daily' => self::dailyRows('Daily Sales', $data),
            'sales-products' => self::productRows($data),
            'sales-employees' => self::employeeRows($data),
            'sales' => self::summaryRows('Sales Report', [
                'Total Sales' => $data['total_sales'] ?? 0,
                'Invoices Count' => $data['invoices_count'] ?? 0,
                'Average Invoice' => $data['average_invoice_value'] ?? 0,
            ]),
            'tailoring' => self::summaryRows('Tailoring Report', [
                'Total Orders' => $data['total_orders'] ?? 0,
                'Ready Orders' => $data['ready_orders'] ?? 0,
                'Late Orders' => $data['late_orders'] ?? 0,
                'In Progress' => $data['in_progress_orders'] ?? 0,
                'Total Revenue' => $data['total_revenue'] ?? 0,
            ]),
            'rental' => self::summaryRows('Rental Report', [
                'Total Orders' => $data['total'] ?? 0,
                'Active' => $data['active'] ?? 0,
                'Returned' => $data['returned'] ?? 0,
                'Overdue' => $data['overdue'] ?? 0,
                'Revenue' => $data['revenue'] ?? 0,
                'Collected' => $data['collected'] ?? 0,
                'Remaining' => $data['remaining'] ?? 0,
            ]),
            'customers' => self::summaryRows('Customers Report', [
                'Total Customers' => $data['total'] ?? 0,
                'VIP Customers' => $data['vip'] ?? 0,
                'New This Month' => $data['new_this_month'] ?? 0,
                'Total Sales' => $data['total_sales'] ?? 0,
            ]),
            'expenses' => self::expenseRows($data),
            'cash' => self::summaryRows('Cash Report', [
                'Total In' => $data['total_in'] ?? 0,
                'Total Out' => $data['total_out'] ?? 0,
                'Net' => $data['net'] ?? 0,
            ]),
            'accounting' => self::accountingRows($data),
            'deliveries' => self::summaryRows('Deliveries Report', self::flattenStats($data)),
            'returns' => self::summaryRows('Returns Report', self::flattenStats($data)),
            'payments' => self::summaryRows('Payments Report', self::flattenStats($data)),
            'suppliers' => self::supplierRows($data),
            'inventory' => self::inventoryRows($data),
            default => self::summaryRows('Report', self::flattenStats($data)),
        };
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{title: string, headers: list<string>, rows: list<list<string|int|float|null>>}
     */
    private static function summaryRows(string $title, array $metrics): array
    {
        $rows = [];
        foreach ($metrics as $label => $value) {
            $rows[] = [(string) $label, is_scalar($value) ? $value : json_encode($value)];
        }

        return [
            'title' => $title,
            'headers' => ['Metric', 'Value'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @return array{title: string, headers: list<string>, rows: list<list<string|int|float|null>>}
     */
    private static function dailyRows(string $title, array $data): array
    {
        $rows = array_map(static fn (array $row): array => [
            $row['date'] ?? '',
            $row['invoices_count'] ?? 0,
            $row['total'] ?? 0,
        ], $data);

        return [
            'title' => $title,
            'headers' => ['Date', 'Invoices', 'Total'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $data
     */
    private static function productRows(array $data): array
    {
        $rows = array_map(static fn (array $row): array => [
            $row['product_name'] ?? '',
            $row['product_code'] ?? '',
            $row['quantity_sold'] ?? 0,
            $row['revenue'] ?? 0,
        ], $data);

        return [
            'title' => 'Sales by Product',
            'headers' => ['Product', 'Code', 'Quantity', 'Revenue'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $data
     */
    private static function employeeRows(array $data): array
    {
        $rows = array_map(static fn (array $row): array => [
            $row['employee_name'] ?? '',
            $row['invoices_count'] ?? 0,
            $row['total_sales'] ?? 0,
        ], $data);

        return [
            'title' => 'Sales by Employee',
            'headers' => ['Employee', 'Invoices', 'Total Sales'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function expenseRows(array $data): array
    {
        $rows = [
            ['Total Amount', $data['total_amount'] ?? 0],
            ['Pending', $data['pending_amount'] ?? 0],
            ['Approved', $data['approved_amount'] ?? 0],
            ['Paid', $data['paid_amount'] ?? 0],
            ['Cancelled', $data['cancelled_amount'] ?? 0],
        ];

        foreach ($data['by_category'] ?? [] as $category) {
            $rows[] = [
                'Category #'.($category['expense_category_id'] ?? '-'),
                $category['total_amount'] ?? 0,
            ];
        }

        return [
            'title' => 'Expenses Report',
            'headers' => ['Category / Metric', 'Amount'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function accountingRows(array $data): array
    {
        $rows = [
            ['Total Income', $data['total_income'] ?? 0],
            ['Total Expenses', $data['total_expenses'] ?? 0],
            ['Net Profit', $data['net_profit'] ?? 0],
        ];

        foreach ($data['cashbox_balances'] ?? [] as $cashbox) {
            $rows[] = ['Cashbox: '.($cashbox['name'] ?? '-'), $cashbox['balance'] ?? 0];
        }

        return [
            'title' => 'Accounting Report',
            'headers' => ['Item', 'Amount'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function supplierRows(array $data): array
    {
        $rows = array_map(static fn (array $row): array => [
            $row['name'] ?? '',
            $row['orders_count'] ?? 0,
            $row['total_purchases'] ?? 0,
            $row['total_paid'] ?? 0,
            $row['balance'] ?? 0,
        ], $data['suppliers'] ?? []);

        return [
            'title' => 'Suppliers Report',
            'headers' => ['Supplier', 'Orders', 'Purchases', 'Paid', 'Balance'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function inventoryRows(array $data): array
    {
        $rows = [
            ['Total Dresses', $data['total_dresses'] ?? 0],
            ['Available', $data['available'] ?? 0],
            ['Rented', $data['rented'] ?? 0],
            ['Sold', $data['sold'] ?? 0],
            ['Utilization %', $data['utilization_percent'] ?? 0],
        ];

        foreach ($data['by_status'] ?? [] as $status => $count) {
            $rows[] = ['Status: '.$status, $count];
        }

        return [
            'title' => 'Inventory Report',
            'headers' => ['Metric', 'Value'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function flattenStats(array $data): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $flat[$key.'_'.$subKey] = $subValue;
                }

                continue;
            }
            $flat[(string) $key] = $value;
        }

        return $flat;
    }
}
