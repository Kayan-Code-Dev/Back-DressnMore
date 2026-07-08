<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('purchase_order_items', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('purchase_order_items', 'item_code')) {
                $table->string('item_code', 120)->nullable()->after('item_name');
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('purchase_order_items', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('purchase_order_items', 'item_code')) {
                $table->dropColumn('item_code');
            }
        });
    }
};
