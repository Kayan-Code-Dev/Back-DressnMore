<?php

namespace Tests\Feature;

use App\Models\Tenant\Branch;
use App\Models\Tenant\HrDepartment;
use App\Models\Tenant\HrEmployee;
use App\Models\Tenant\HrJobTitle;

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

    public function test_show_employee_details(): void
    {
        $headers = $this->hrHeaders();

        $employee = HrEmployee::query()->create(array_merge($this->employeePayload('EMP-020', null), [
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
    private function employeePayload(string $code, ?string $nationalId): array
    {
        return [
            'employee_code' => $code,
            'full_name' => 'Test Employee',
            'phone' => '+966500000001',
            'email' => 'employee@test.com',
            'national_id' => $nationalId,
            'employment_type' => 'full_time',
            'status' => 'active',
            'joining_date' => '2024-01-01',
            'base_salary' => 5000,
            'salary_type' => 'monthly',
            'working_hours_per_day' => 8,
        ];
    }
}
