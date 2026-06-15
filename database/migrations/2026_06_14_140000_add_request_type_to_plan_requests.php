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
            if (! Schema::connection($this->connection)->hasColumn('plan_requests', 'request_type')) {
                $table->string('request_type', 32)->default('signup')->after('plan_id');
            }
            if (! Schema::connection($this->connection)->hasColumn('plan_requests', 'source_tenant_id')) {
                $table->unsignedBigInteger('source_tenant_id')->nullable()->after('request_type');
                $table->foreign('source_tenant_id')->references('id')->on('tenants')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('plan_requests', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('plan_requests', 'source_tenant_id')) {
                $table->dropForeign(['source_tenant_id']);
                $table->dropColumn('source_tenant_id');
            }
            if (Schema::connection($this->connection)->hasColumn('plan_requests', 'request_type')) {
                $table->dropColumn('request_type');
            }
        });
    }
};
