<?php

namespace Database\Seeders;

use Database\Seeders\Central\PaymentGatewaySeeder;
use Database\Seeders\Central\PlanFeatureSeeder;
use Database\Seeders\Central\PlanSeeder;
use Database\Seeders\Central\SuperAdminSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SuperAdminSeeder::class,
            PlanSeeder::class,
            PlanFeatureSeeder::class,
            PaymentGatewaySeeder::class,
        ]);
    }
}
