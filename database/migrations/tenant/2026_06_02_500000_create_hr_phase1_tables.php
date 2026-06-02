<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('hr_departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique('name');
            $table->index('status');
        });

        Schema::connection($this->connection)->create('hr_job_titles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->string('title', 120);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('department_id');
            $table->index('title');
            $table->index('status');
        });

        Schema::connection($this->connection)->create('hr_employees', function (Blueprint $table): void {
            $table->id();
            $table->string('employee_code', 50);
            $table->string('full_name', 190);
            $table->string('avatar_path')->nullable();
            $table->string('phone', 30);
            $table->string('email', 190)->nullable();
            $table->string('national_id', 50)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('job_title_id')->nullable()->constrained('hr_job_titles')->nullOnDelete();
            $table->string('employment_type', 20);
            $table->string('status', 20)->default('active');
            $table->date('joining_date');
            $table->date('leaving_date')->nullable();
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->string('salary_type', 20);
            $table->decimal('working_hours_per_day', 5, 2)->nullable();
            $table->string('emergency_contact_name', 120)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('employee_code');
            $table->index('national_id');
            $table->index('phone');
            $table->index('branch_id');
            $table->index('department_id');
            $table->index('job_title_id');
            $table->index('employment_type');
            $table->index('status');
            $table->index('joining_date');
        });

        Schema::connection($this->connection)->create('hr_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('document_type', 30);
            $table->string('file_name', 255);
            $table->string('file_path', 500)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status', 30)->default('valid');
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('employee_id');
            $table->index('document_type');
            $table->index('status');
            $table->index('expiry_date');
        });

        Schema::connection($this->connection)->create('hr_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique('key');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('hr_settings');
        Schema::connection($this->connection)->dropIfExists('hr_documents');
        Schema::connection($this->connection)->dropIfExists('hr_employees');
        Schema::connection($this->connection)->dropIfExists('hr_job_titles');
        Schema::connection($this->connection)->dropIfExists('hr_departments');
    }
};
