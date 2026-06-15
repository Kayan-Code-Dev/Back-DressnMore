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
            if (! Schema::connection($this->connection)->hasColumn('purchase_orders', 'deposit_amount')) {
                $table->decimal('deposit_amount', 12, 2)->default(0)->after('remaining_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('purchase_orders', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('purchase_orders', 'deposit_amount')) {
                $table->dropColumn('deposit_amount');
            }
        });
    }
};
