<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('purchase_orders', function (Blueprint $table): void {
            $table->timestamp('received_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('purchase_orders', function (Blueprint $table): void {
            $table->dropColumn('received_at');
        });
    }
};
