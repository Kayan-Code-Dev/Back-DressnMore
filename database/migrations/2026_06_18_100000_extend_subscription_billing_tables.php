<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection($this->connection)->table('tenants', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('tenants', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('subscription_ends_at');
            }
            if (! Schema::connection($this->connection)->hasColumn('tenants', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            }
        });

        Schema::connection($this->connection)->table('plan_requests', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('plan_requests', 'old_plan_id')) {
                $table->foreignId('old_plan_id')->nullable()->after('plan_id')->constrained('plans')->nullOnDelete();
            }
            if (! Schema::connection($this->connection)->hasColumn('plan_requests', 'tenant_notes')) {
                $table->text('tenant_notes')->nullable()->after('admin_notes');
            }
            if (! Schema::connection($this->connection)->hasColumn('plan_requests', 'billing_cycle')) {
                $table->string('billing_cycle', 32)->nullable()->after('tenant_notes');
            }
        });

        Schema::connection($this->connection)->table('payments', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('payments', 'plan_request_id')) {
                $table->foreignId('plan_request_id')->nullable()->after('plan_id')->constrained('plan_requests')->nullOnDelete();
            }
            if (! Schema::connection($this->connection)->hasColumn('payments', 'currency')) {
                $table->string('currency', 8)->default('EGP')->after('amount');
            }
            if (! Schema::connection($this->connection)->hasColumn('payments', 'proof_path')) {
                $table->string('proof_path')->nullable()->after('reference');
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('payments', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('payments', 'proof_path')) {
                $table->dropColumn('proof_path');
            }
            if (Schema::connection($this->connection)->hasColumn('payments', 'currency')) {
                $table->dropColumn('currency');
            }
            if (Schema::connection($this->connection)->hasColumn('payments', 'plan_request_id')) {
                $table->dropForeign(['plan_request_id']);
                $table->dropColumn('plan_request_id');
            }
        });

        Schema::connection($this->connection)->table('plan_requests', function (Blueprint $table): void {
            foreach (['billing_cycle', 'tenant_notes', 'old_plan_id'] as $col) {
                if (Schema::connection($this->connection)->hasColumn('plan_requests', $col)) {
                    if ($col === 'old_plan_id') {
                        $table->dropForeign(['old_plan_id']);
                    }
                    $table->dropColumn($col);
                }
            }
        });

        Schema::connection($this->connection)->table('tenants', function (Blueprint $table): void {
            foreach (['cancellation_reason', 'cancelled_at'] as $col) {
                if (Schema::connection($this->connection)->hasColumn('tenants', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
