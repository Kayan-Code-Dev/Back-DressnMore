<?php

namespace Tests\Feature;

use App\Models\Tenant\Branch;
use App\Models\Tenant\HrDepartment;
use App\Models\Tenant\HrEmployee;
use App\Models\Tenant\HrJobTitle;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;

class HrEmployeeApiTest extends TenantHrTestCase
{
    /**
     * @return array<string, string>
     */
    private function hrHeaders(): array
    {
        $user = $this->createTenantUserWithPermissions($this->allHrPhase1Permissions());

        return $this->authHeaders($user);
    }

    public function test_create_employee_and_unique_constraints(): void
    {
        $headers = $this->hrHeaders();

        $payload = $this->employeePayload('EMP-001', '1023456789');

        $this->postJson('/api/tenant/hr/employees', $payload, $headers)
            ->assertCreated()
            ->assertJsonPath('data.employee_code', 'EMP-001');

        $this->postJson('/api/tenant/hr/employees', $payload, $headers)
            ->assertStatus(422);

        $duplicateNational = $payload;
        $duplicateNational['employee_code'] = 'EMP-002';
        $this->postJson('/api/tenant/hr/employees', $duplicateNational, $headers)
            ->assertStatus(422);
    }

    public function test_list_filters_update_status_and_soft_delete(): void
    {
        $headers = $this->hrHeaders();

        $branch = Branch::query()->create(['name' => 'Main', 'branch_code' => 'BR-1', 'status' => 'active']);
        $department = HrDepartment::query()->create(['name' => 'Sales', 'status' => 'active']);
        $jobTitle = HrJobTitle::query()->create(['title' => 'Sales Rep', 'department_id' => $department->id, 'status' => 'active']);

        $create = $this->postJson('/api/tenant/hr/employees', array_merge($this->employeePayload('EMP-010', null), [
            'full_name' => 'Reem Al-Otaibi',
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'job_title_id' => $jobTitle->id,
        ]), $headers)->assertCreated();
        $employeeId = (int) $create->json('data.id');

        $this->getJson('/api/tenant/hr/employees?search=Reem&branch_id='.$branch->id.'&department_id='.$department->id.'&status=active&employment_type=full_time', $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->putJson("/api/tenant/hr/employees/{$employeeId}", [
            'phone' => '+966501111111',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.phone', '+966501111111');

        $this->patchJson("/api/tenant/hr/employees/{$employeeId}/status", [
            'status' => 'terminated',
            'leaving_date' => now()->toDateString(),
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'terminated');

        $this->getJson("/api/tenant/hr/employees/{$employeeId}/summary", $headers)
            ->assertOk()
            ->assertJsonPath('data.employee.id', $employeeId)
            ->assertJsonPath('data.documents_count', 0);

        $this->deleteJson("/api/tenant/hr/employees/{$employeeId}", [], $headers)->assertOk();
        $this->assertSoftDeleted('hr_employees', ['id' => $employeeId], 'tenant');
    }

    public function test_create_employee_with_user_account_and_custom_permissions(): void
    {
        $headers = $this->hrHeaders();

        $permissionIds = Permission::query()
            ->whereIn('key', ['hr.employees.view', 'hr.attendance.view'])
            ->pluck('id')
            ->all();
        $this->assertNotEmpty($permissionIds);

        $email = 'hr.staff.'.uniqid().'@tenant.test';
        $payload = array_merge($this->employeePayload('EMP-100', null), [
            'user_account' => [
                'email' => $email,
                'password' => 'SecretPass1',
                'password_confirmation' => 'SecretPass1',
                'permission_ids' => $permissionIds,
            ],
        ]);

        $response = $this->postJson('/api/tenant/hr/employees', $payload, $headers)
            ->assertCreated()
            ->assertJsonPath('data.user_account.email', $email);

        $userId = (int) $response->json('data.user_id');
        $user = User::query()->findOrFail($userId);
        $this->assertSame($email, $user->email);
        $this->assertTrue($user->roles()->exists());
    }

    public function test_list_hr_access_roles_and_permissions(): void
    {
        $headers = $this->hrHeaders();

        Role::query()->firstOrCreate(['slug' => 'cashier'], ['name' => 'Cashier']);

        $this->getJson('/api/tenant/hr/access/roles', $headers)
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'permission_ids']]]);

        $this->getJson('/api/tenant/hr/access/permissions', $headers)
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'key', 'name', 'group']]]);
    }

    public function test_show_employee_details(): void
    {
        $headers = $this->hrHeaders();

        $employee = HrEmployee::query()->create(array_merge($this->employeeAttributes('EMP-020', null), [
            'full_name' => 'Detail Employee',
        ]));

        $this->getJson('/api/tenant/hr/employees/'.$employee->id, $headers)
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Detail Employee')
            ->assertJsonPath('data.employee_code', 'EMP-020');
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function employeeAttributes(string $code, ?string $nationalId): array
    {
        $email = strtolower($code).'@hr-employee.test';

        return [
            'employee_code' => $code,
            'full_name' => 'Test Employee',
            'phone' => '+966500000001',
            'email' => $email,
            'national_id' => $nationalId,
            'employment_type' => 'full_time',
            'status' => 'active',
            'joining_date' => '2024-01-01',
            'base_salary' => 5000,
            'salary_type' => 'monthly',
            'working_hours_per_day' => 8,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(string $code, ?string $nationalId): array
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'hr-test-staff'],
            ['name' => 'HR Test Staff']
        );
        $permissionIds = Permission::query()
            ->whereIn('key', ['hr.employees.view', 'hr.dashboard.view'])
            ->pluck('id')
            ->all();
        if ($permissionIds !== []) {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }

        $email = strtolower($code).'@hr-employee.test';

        return [
            'employee_code' => $code,
            'full_name' => 'Test Employee',
            'phone' => '+966500000001',
            'email' => $email,
            'national_id' => $nationalId,
            'employment_type' => 'full_time',
            'status' => 'active',
            'joining_date' => '2024-01-01',
            'base_salary' => 5000,
            'salary_type' => 'monthly',
            'working_hours_per_day' => 8,
            'user_account' => [
                'email' => $email,
                'password' => 'SecretPass1',
                'password_confirmation' => 'SecretPass1',
                'role_id' => $role->id,
            ],
        ];
    }
}
