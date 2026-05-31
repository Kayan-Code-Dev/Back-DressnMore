<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('employees', function (Blueprint $table): void {
            $table->id();
            $table->string('employee_code')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('job_title')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('employment_status')->default('active');
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->date('hire_date')->nullable();
            $table->decimal('transport_allowance', 12, 2)->default(0);
            $table->decimal('housing_allowance', 12, 2)->default(0);
            $table->decimal('other_allowances', 12, 2)->default(0);
            $table->json('roles')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('employee_custodies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('type');
            $table->string('description');
            $table->decimal('value', 12, 2)->default(0);
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('employee_salaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('period');
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);
            $table->string('status')->default('unpaid');
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('workshops', function (Blueprint $table): void {
            $table->id();
            $table->string('workshop_code')->unique();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('inventory_name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('workshop_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->string('transfer_code')->unique();
            $table->string('from_branch')->nullable();
            $table->string('to_workshop')->nullable();
            $table->string('item_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('workshop_cloths', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->string('cloth_code')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('product_name')->nullable();
            $table->string('workshop_status')->default('processing');
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('factories', function (Blueprint $table): void {
            $table->id();
            $table->string('factory_code')->unique();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('inventory_name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('category')->default('system');
            $table->string('priority')->default('normal');
            $table->timestamp('read_at')->nullable();
            $table->string('action_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('notifications');
        Schema::connection($this->connection)->dropIfExists('factories');
        Schema::connection($this->connection)->dropIfExists('workshop_cloths');
        Schema::connection($this->connection)->dropIfExists('workshop_transfers');
        Schema::connection($this->connection)->dropIfExists('workshops');
        Schema::connection($this->connection)->dropIfExists('employee_salaries');
        Schema::connection($this->connection)->dropIfExists('employee_custodies');
        Schema::connection($this->connection)->dropIfExists('employees');
    }
};
