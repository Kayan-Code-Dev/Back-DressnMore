<?php

namespace Tests\Feature;

use App\Models\Tenant\HrDocument;
use App\Models\Tenant\HrEmployee;
use Carbon\CarbonImmutable;

class HrDocumentApiTest extends TenantHrTestCase
{
    public function test_document_metadata_crud_filters_and_expiry_alerts(): void
    {
        $user = $this->createTenantUserWithPermissions([
            'hr.employees.view',
            'hr.documents.view',
            'hr.documents.upload',
            'hr.documents.delete',
        ]);
        $headers = $this->authHeaders($user);

        $employee = HrEmployee::query()->create([
            'employee_code' => 'EMP-DOC-1',
            'full_name' => 'Doc Employee',
            'phone' => '+966500000099',
            'employment_type' => 'full_time',
            'status' => 'active',
            'joining_date' => '2024-01-01',
            'base_salary' => 4000,
            'salary_type' => 'monthly',
        ]);

        $create = $this->postJson('/api/tenant/hr/documents', [
            'employee_id' => $employee->id,
            'document_type' => 'contract',
            'file_name' => 'contract.pdf',
            'issue_date' => '2024-01-01',
            'expiry_date' => '2024-06-01',
        ], $headers);
        $create->assertCreated()->assertJsonPath('data.file_name', 'contract.pdf');
        $documentId = (int) $create->json('data.id');

        $this->postJson('/api/tenant/hr/documents', [
            'employee_id' => $employee->id,
            'document_type' => 'contract',
            'file_name' => 'bad.pdf',
            'issue_date' => '2024-06-01',
            'expiry_date' => '2024-01-01',
        ], $headers)->assertStatus(422);

        HrDocument::query()->create([
            'employee_id' => $employee->id,
            'document_type' => 'insurance',
            'file_name' => 'insurance.pdf',
            'expiry_date' => CarbonImmutable::today()->addDays(10),
            'status' => 'expiring_soon',
        ]);

        $this->getJson('/api/tenant/hr/documents?employee_id='.$employee->id.'&status=expiring_soon', $headers)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/tenant/hr/documents/expiry-alerts', $headers)
            ->assertOk()
            ->assertJson(fn ($json) => $json->where('success', true)->etc());

        $this->deleteJson('/api/tenant/hr/documents/'.$documentId, [], $headers)->assertOk();
        $this->assertDatabaseMissing('hr_documents', ['id' => $documentId], 'tenant');
    }
}
