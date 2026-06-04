<?php

namespace Tests\Feature;

use App\Models\Tenant\Branch;
use App\Models\Tenant\HrEmployee;

class HrPhase2ApiTest extends TenantHrTestCase
{
    /**
     * @return array<string, string>
     */
    private function hrPhase2Headers(): array
    {
        $user = $this->createTenantUserWithPermissions([
            ...$this->allHrPhase1Permissions(),
            'hr.shifts.view',
            'hr.shifts.create',
            'hr.shifts.update',
            'hr.shifts.delete',
            'hr.attendance.view',
            'hr.attendance.create',
            'hr.attendance.update',
            'hr.leaves.view',
            'hr.leaves.create',
            'hr.leaves.status',
        ]);

        return $this->authHeaders($user);
    }

    public function test_shift_attendance_and_leave_flow(): void
    {
        $headers = $this->hrPhase2Headers();

        $branch = Branch::query()->create(['name' => 'HQ', 'branch_code' => 'HQ1', 'status' => 'active']);

        $employee = HrEmployee::query()->create([
            'employee_code' => 'PH2-001',
            'full_name' => 'Phase 2 Employee',
            'phone' => '+966501000111',
            'email' => 'phase2.employee@test.com',
            'employment_type' => 'full_time',
            'status' => 'active',
            'joining_date' => '2026-01-01',
            'base_salary' => 4500,
            'salary_type' => 'monthly',
            'working_hours_per_day' => 8,
            'branch_id' => $branch->id,
        ]);

        $createShift = $this->postJson('/api/tenant/hr/shifts', [
            'name' => 'Morning Shift',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'working_days' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
            'branch_id' => $branch->id,
            'status' => 'active',
        ], $headers)->assertCreated();

        $shiftId = (int) $createShift->json('data.id');

        $this->putJson("/api/tenant/hr/shifts/{$shiftId}", [
            'name' => 'Morning Shift Updated',
            'grace_minutes' => 10,
        ], $headers)->assertOk()->assertJsonPath('data.name', 'Morning Shift Updated');

        $attendance = $this->postJson('/api/tenant/hr/attendance', [
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
            'shift_id' => $shiftId,
            'check_in' => '09:10',
            'check_out' => '17:00',
            'late_minutes' => 10,
            'status' => 'late',
        ], $headers)->assertOk();
        $attendanceId = (int) $attendance->json('data.id');

        $this->putJson("/api/tenant/hr/attendance/{$attendanceId}", [
            'status' => 'present',
            'late_minutes' => 0,
        ], $headers)->assertOk()->assertJsonPath('data.status', 'present');

        $this->getJson('/api/tenant/hr/attendance?employee_id='.$employee->id, $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $leaveCreate = $this->postJson('/api/tenant/hr/leaves', [
            'employee_id' => $employee->id,
            'type' => 'annual',
            'from_date' => now()->toDateString(),
            'to_date' => now()->addDay()->toDateString(),
            'reason' => 'Family vacation',
        ], $headers)->assertCreated();

        $leaveId = (int) $leaveCreate->json('data.id');
        $this->patchJson("/api/tenant/hr/leaves/{$leaveId}/status", [
            'status' => 'approved',
            'review_notes' => 'Approved',
        ], $headers)->assertOk()->assertJsonPath('data.status', 'approved');

        $this->getJson('/api/tenant/hr/leaves?employee_id='.$employee->id.'&status=approved', $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/tenant/hr/dashboard', $headers)
            ->assertOk()
            ->assertJsonPath('data.kpis.pending_requests', 0)
            ->assertJsonPath('data.kpis.total_employees', 1);

        $this->deleteJson("/api/tenant/hr/shifts/{$shiftId}", [], $headers)->assertOk();
        $this->assertDatabaseMissing('hr_shifts', ['id' => $shiftId], 'tenant');
        $this->assertDatabaseHas('hr_attendance_records', ['id' => $attendanceId], 'tenant');
        $this->assertDatabaseHas('hr_leave_requests', ['id' => $leaveId], 'tenant');
    }
}
