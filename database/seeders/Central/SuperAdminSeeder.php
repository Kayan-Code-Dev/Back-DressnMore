<?php

namespace Database\Seeders\Central;

use App\Models\Central\SuperAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = (string) env('PLATFORM_ADMIN_NAME', 'Platform Admin');
        $email = strtolower((string) env('PLATFORM_ADMIN_EMAIL', 'admin@example.com'));
        $password = (string) env('PLATFORM_ADMIN_PASSWORD', 'change_me');

        SuperAdmin::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'status' => 'active',
            ]
        );
    }
}
