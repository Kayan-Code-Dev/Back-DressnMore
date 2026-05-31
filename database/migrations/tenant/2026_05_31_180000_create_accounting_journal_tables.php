<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('type', 32)->default('asset');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('entry_number')->unique();
            $table->date('entry_date');
            $table->string('type', 32)->default('normal');
            $table->string('source_type', 32)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft');
            $table->decimal('total_debit', 14, 2)->default(0);
            $table->decimal('total_credit', 14, 2)->default(0);
            $table->decimal('difference', 14, 2)->default(0);
            $table->boolean('is_balanced')->default(false);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('reversed_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'entry_date']);
            $table->index(['source_type', 'source_id']);
            $table->index('branch_id');
        });

        Schema::connection('tenant')->create('journal_entry_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts');
            $table->string('account_code', 32);
            $table->string('account_name');
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('description')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->timestamps();

            $table->index('journal_entry_id');
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('journal_entry_lines');
        Schema::connection('tenant')->dropIfExists('journal_entries');
        Schema::connection('tenant')->dropIfExists('accounts');
    }
};
