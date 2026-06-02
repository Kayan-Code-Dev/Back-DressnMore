<?php

namespace Tests\Feature;

class HrDepartmentApiTest extends TenantHrTestCase
{
    public function test_authorized_user_can_crud_department(): void
    {
        $user = $this->createTenantUserWithPermissions([
            'hr.departments.view',
            'hr.departments.create',
            'hr.departments.update',
            'hr.departments.delete',
        ]);
        $headers = $this->authHeaders($user);

        $create = $this->postJson('/api/tenant/hr/departments', [
            'name' => 'Sales',
            'status' => 'active',
        ], $headers);
        $create->assertCreated()->assertJsonPath('data.name', 'Sales');
        $departmentId = (int) $create->json('data.id');

        $this->getJson('/api/tenant/hr/departments', $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->putJson("/api/tenant/hr/departments/{$departmentId}", [
            'name' => 'Sales Updated',
            'status' => 'active',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Sales Updated');

        $this->deleteJson("/api/tenant/hr/departments/{$departmentId}", [], $headers)
            ->assertOk();

        $this->getJson('/api/tenant/hr/departments', $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_unauthorized_user_cannot_access_departments(): void
    {
        $user = $this->createTenantUserWithPermissions(['customers.view']);
        $headers = $this->authHeaders($user);

        $this->getJson('/api/tenant/hr/departments', $headers)->assertForbidden();
        $this->postJson('/api/tenant/hr/departments', ['name' => 'Ops'], $headers)->assertForbidden();
    }
}
