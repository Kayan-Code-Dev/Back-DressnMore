<?php

namespace Tests\Feature;

use App\Models\Tenant\HrDepartment;
use App\Models\Tenant\HrEmployee;
use App\Models\Tenant\HrJobTitle;

class HrDashboardApiTest extends TenantHrTestCase
{
    public function test_dashboard_returns_correct_counts(): void
    {
        $user = $this->createTenantUserWithPermissions(['hr.dashboard.view']);
        $headers = $this->authHeaders($user);

        HrDepartment::query()->create(['name' => 'Sales', 'status' => 'active']);
        HrJobTitle::query()->create(['title' => 'Sales Rep', 'status' => 'active']);
        HrEmployee::query()->create([
            'employee_code' => 'EMP-A1',
            'full_name' => 'Active One',
            'phone' => '+966500000001',
            'employment_type' => 'full_time',
            'status' => 'active',
            'joining_date' => '2024-01-01',
            'base_salary' => 5000,
            'salary_type' => 'monthly',
        ]);
        HrEmployee::query()->create([
            'employee_code' => 'EMP-I1',
            'full_name' => 'Inactive One',
            'phone' => '+966500000002',
            'employment_type' => 'full_time',
            'status' => 'inactive',
            'joining_date' => '2024-01-01',
            'base_salary' => 5000,
            'salary_type' => 'monthly',
        ]);

        $this->getJson('/api/tenant/hr/dashboard', $headers)
            ->assertOk()
            ->assertJsonPath('data.kpis.total_employees', 2)
            ->assertJsonPath('data.kpis.active_employees', 1)
            ->assertJsonPath('data.kpis.inactive_employees', 1)
            ->assertJsonPath('data.kpis.departments_count', 1)
            ->assertJsonPath('data.kpis.job_titles_count', 1)
            ->assertJsonPath('data.attendance_snapshot.present', 0);
    }
}
