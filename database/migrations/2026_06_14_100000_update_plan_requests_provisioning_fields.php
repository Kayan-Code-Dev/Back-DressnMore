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
            if (! Schema::connection($this->connection)->hasColumn('plan_requests', 'provision_password')) {
                $table->text('provision_password')->nullable()->after('password');
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('plan_requests', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('plan_requests', 'provision_password')) {
                $table->dropColumn('provision_password');
            }
        });
    }
};
