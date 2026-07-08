<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('hr_employees', function (Blueprint $table): void {
            $table->string('commission_type', 20)->default('none')->after('salary_type');
            $table->decimal('commission_fixed_amount', 12, 2)->nullable()->after('commission_type');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('commission_fixed_amount');
            $table->string('commission_activity', 20)->default('all')->after('commission_rate');
        });

        Schema::connection($this->connection)->create('hr_payroll_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('payroll_month');
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('paid');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('cashbox_id')->nullable()->constrained('cashboxes')->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'payroll_month']);
            $table->index(['payroll_month', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('hr_payroll_payments');

        Schema::connection($this->connection)->table('hr_employees', function (Blueprint $table): void {
            $table->dropColumn([
                'commission_type',
                'commission_fixed_amount',
                'commission_rate',
                'commission_activity',
            ]);
        });
    }
};
