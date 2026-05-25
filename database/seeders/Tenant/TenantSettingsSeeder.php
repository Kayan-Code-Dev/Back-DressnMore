<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Setting;
use Illuminate\Database\Seeder;

class TenantSettingsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'app.settings'],
            [
                'value' => [
                    'timezone' => 'UTC',
                    'currency' => 'USD',
                ],
            ]
        );
    }
}
