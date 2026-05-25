<?php

namespace Database\Seeders\Central;

use App\Models\Central\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'price' => 49.00,
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'description' => 'Starter plan for small ateliers',
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 99.00,
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'description' => 'Professional plan for growing ateliers',
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price' => 199.00,
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'description' => 'Advanced plan for multi-branch ateliers',
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
