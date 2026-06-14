<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection($this->connection)->table('plan_requests', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('plan_requests', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('payment_gateway_id');
            }
            if (! Schema::connection($this->connection)->hasColumn('plan_requests', 'payment_proof_path')) {
                $table->string('payment_proof_path')->nullable()->after('payment_reference');
            }
            if (! Schema::connection($this->connection)->hasColumn('plan_requests', 'payment_submitted_at')) {
                $table->timestamp('payment_submitted_at')->nullable()->after('payment_proof_path');
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('plan_requests', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('plan_requests', 'payment_submitted_at')) {
                $table->dropColumn('payment_submitted_at');
            }
            if (Schema::connection($this->connection)->hasColumn('plan_requests', 'payment_proof_path')) {
                $table->dropColumn('payment_proof_path');
            }
            if (Schema::connection($this->connection)->hasColumn('plan_requests', 'payment_reference')) {
                $table->dropColumn('payment_reference');
            }
        });
    }
};
