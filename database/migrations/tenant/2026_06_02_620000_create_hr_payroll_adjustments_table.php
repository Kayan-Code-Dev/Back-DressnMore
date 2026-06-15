<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('hr_payroll_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('type', 32);
            $table->decimal('amount', 12, 2);
            $table->date('effective_month');
            $table->string('status', 32)->default('approved');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['effective_month', 'type']);
            $table->index(['employee_id', 'effective_month']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('hr_payroll_adjustments');
    }
};
