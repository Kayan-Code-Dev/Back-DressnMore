<?php

namespace Database\Seeders\Central;

use App\Models\Central\Plan;
use App\Services\Platform\PlanService;
use Illuminate\Database\Seeder;

class PlanFeatureSeeder extends Seeder
{
    public function run(): void
    {
        /** @var PlanService $planService */
        $planService = app(PlanService::class);

        $matrix = [
            'basic' => [
                'dashboard.enabled' => true,
                'customers.enabled' => true,
                'categories.enabled' => true,
                'subcategories.enabled' => true,
                'dresses.enabled' => true,
                'branches.enabled' => true,
                'invoices.enabled' => true,
                'payments.enabled' => true,
                'branches.max' => 1,
                'users.max' => 3,
            ],
            'pro' => [
                'ai_assistant.enabled' => true,
                'dashboard.enabled' => true,
                'customers.enabled' => true,
                'categories.enabled' => true,
                'subcategories.enabled' => true,
                'dresses.enabled' => true,
                'inventory.enabled' => true,
                'branches.enabled' => true,
                'invoices.enabled' => true,
                'orders.enabled' => true,
                'payments.enabled' => true,
                'deliveries.enabled' => true,
                'returns.enabled' => true,
                'suppliers.enabled' => true,
                'purchase_orders.enabled' => true,
                'expenses.enabled' => true,
                'cashboxes.enabled' => true,
                'cash_movements.enabled' => true,
                'reports.enabled' => true,
                'branches.max' => 3,
                'users.max' => 10,
            ],
            'enterprise' => [
                'dashboard.enabled' => true,
                'customers.enabled' => true,
                'categories.enabled' => true,
                'subcategories.enabled' => true,
                'dresses.enabled' => true,
                'inventory.enabled' => true,
                'branches.enabled' => true,
                'invoices.enabled' => true,
                'orders.enabled' => true,
                'payments.enabled' => true,
                'deliveries.enabled' => true,
                'returns.enabled' => true,
                'suppliers.enabled' => true,
                'purchase_orders.enabled' => true,
                'supplier_payments.enabled' => true,
                'expenses.enabled' => true,
                'cashboxes.enabled' => true,
                'cash_movements.enabled' => true,
                'reports.enabled' => true,
                'accounting.enabled' => true,
                'branches.max' => 0,
                'users.max' => 0,
            ],
        ];

        foreach ($matrix as $slug => $features) {
            $plan = Plan::query()->where('slug', $slug)->first();
            if (! $plan) {
                continue;
            }

            $planService->syncFeatures($plan, $features);
        }
    }
}