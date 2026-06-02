<?php

namespace Tests\Feature;

use App\Models\Tenant\HrDepartment;

class HrJobTitleApiTest extends TenantHrTestCase
{
    public function test_job_title_crud_and_department_relation(): void
    {
        $user = $this->createTenantUserWithPermissions([
            'hr.departments.view',
            'hr.job_titles.view',
            'hr.job_titles.create',
            'hr.job_titles.update',
            'hr.job_titles.delete',
        ]);
        $headers = $this->authHeaders($user);

        $department = HrDepartment::query()->create(['name' => 'Tailoring', 'status' => 'active']);

        $create = $this->postJson('/api/tenant/hr/job-titles', [
            'title' => 'Tailor',
            'department_id' => $department->id,
            'status' => 'active',
        ], $headers);
        $create->assertCreated()
            ->assertJsonPath('data.title', 'Tailor')
            ->assertJsonPath('data.department.id', $department->id);
        $jobTitleId = (int) $create->json('data.id');

        $this->getJson("/api/tenant/hr/job-titles/{$jobTitleId}", $headers)
            ->assertOk()
            ->assertJsonPath('data.department.name', 'Tailoring');

        $this->putJson("/api/tenant/hr/job-titles/{$jobTitleId}", [
            'title' => 'Senior Tailor',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.title', 'Senior Tailor');

        $this->deleteJson("/api/tenant/hr/job-titles/{$jobTitleId}", [], $headers)->assertOk();
    }
}
