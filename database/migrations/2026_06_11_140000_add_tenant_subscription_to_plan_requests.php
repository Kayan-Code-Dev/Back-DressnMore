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
            $table->foreignId('tenant_id')->nullable()->after('status')->constrained('tenants')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->after('tenant_id')->constrained('subscriptions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('plan_requests', function (Blueprint $table): void {
            $table->dropColumn(['tenant_id', 'subscription_id']);
        });
    }
};

