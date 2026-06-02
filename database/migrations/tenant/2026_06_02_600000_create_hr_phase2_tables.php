<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('hr_shifts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->json('working_days');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('branch_id');
            $table->index('status');
            $table->unique(['name', 'branch_id']);
        });

        Schema::connection($this->connection)->create('hr_attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('shift_id')->nullable()->constrained('hr_shifts')->nullOnDelete();
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->string('status', 20)->default('present');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index('date');
            $table->index('shift_id');
            $table->index('status');
        });

        Schema::connection($this->connection)->create('hr_leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('type', 20);
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('days', 5, 2);
            $table->string('status', 20)->default('pending');
            $table->string('reason', 255);
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index('employee_id');
            $table->index('type');
            $table->index('status');
            $table->index(['from_date', 'to_date']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('hr_leave_requests');
        Schema::connection($this->connection)->dropIfExists('hr_attendance_records');
        Schema::connection($this->connection)->dropIfExists('hr_shifts');
    }
};
