<?php

namespace Tests\Feature;

use App\Models\Tenant\HrDocument;
use App\Models\Tenant\HrEmployee;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class HrDocumentApiTest extends TenantHrTestCase
{
    public function test_document_metadata_crud_filters_and_expiry_alerts(): void
    {
        Storage::fake('local');

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

        $create = $this->post('/api/tenant/hr/documents', [
            'employee_id' => $employee->id,
            'document_type' => 'contract',
            'file' => UploadedFile::fake()->create('contract.pdf', 32, 'application/pdf'),
            'issue_date' => '2024-01-01',
            'expiry_date' => '2024-06-01',
        ], $headers);
        $create->assertCreated()->assertJsonPath('data.file_name', 'contract.pdf');
        $this->assertStringStartsWith(
            'tenants/'.$this->tenant->id.'/hr/documents/'.$employee->id.'/',
            (string) $create->json('data.file_path'),
        );
        Storage::disk('local')->assertExists((string) $create->json('data.file_path'));
        $documentId = (int) $create->json('data.id');

        $download = $this->get('/api/tenant/hr/documents/'.$documentId.'/download', $headers);
        $download->assertOk();
        $this->assertStringContainsString('contract.pdf', (string) $download->headers->get('content-disposition'));

        $viewOnlyUser = $this->createTenantUserWithPermissions([
            'hr.documents.view',
        ]);
        $this->get('/api/tenant/hr/documents/'.$documentId.'/download', $this->authHeaders($viewOnlyUser))
            ->assertForbidden();

        $this->post('/api/tenant/hr/documents', [
            'employee_id' => $employee->id,
            'document_type' => 'contract',
            'file' => UploadedFile::fake()->create('bad.pdf', 32, 'application/pdf'),
            'issue_date' => '2024-06-01',
            'expiry_date' => '2024-01-01',
        ], $headers)->assertStatus(422);

        $this->postJson('/api/tenant/hr/documents', [
            'employee_id' => $employee->id,
            'document_type' => 'contract',
            'file_name' => 'manual.pdf',
            'file_path' => '/tmp/manual.pdf',
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

        $storedPath = (string) $create->json('data.file_path');
        Storage::disk('local')->delete($storedPath);
        $this->getJson('/api/tenant/hr/documents/'.$documentId.'/download', $headers)
            ->assertNotFound()
            ->assertJsonPath('message', 'Document file not found');

        Storage::disk('local')->put($storedPath, 'restored');
        $this->deleteJson('/api/tenant/hr/documents/'.$documentId, [], $headers)->assertOk();
        $this->assertDatabaseMissing('hr_documents', ['id' => $documentId], 'tenant');
        Storage::disk('local')->assertMissing($storedPath);
    }
}
