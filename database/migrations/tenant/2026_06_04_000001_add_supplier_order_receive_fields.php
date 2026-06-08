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
            if (! Schema::connection($this->connection)->hasColumn('purchase_orders', 'expected_delivery_date')) {
                $table->date('expected_delivery_date')->nullable()->after('order_date');
            }
            if (! Schema::connection($this->connection)->hasColumn('purchase_orders', 'deposit_amount')) {
                $table->decimal('deposit_amount', 12, 2)->default(0)->after('remaining_amount');
            }
            if (! Schema::connection($this->connection)->hasColumn('purchase_orders', 'inventory_received')) {
                $table->boolean('inventory_received')->default(false)->after('deposit_amount');
            }
            if (! Schema::connection($this->connection)->hasColumn('purchase_orders', 'received_at')) {
                $table->dateTime('received_at')->nullable()->after('inventory_received');
            }
            if (! Schema::connection($this->connection)->hasColumn('purchase_orders', 'received_by')) {
                $table->unsignedBigInteger('received_by')->nullable()->after('received_at');
            }
        });

        Schema::connection($this->connection)->table('purchase_order_items', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('purchase_order_items', 'code')) {
                $table->string('code')->nullable()->after('purchase_order_id');
            }
            if (! Schema::connection($this->connection)->hasColumn('purchase_order_items', 'dress_category_id')) {
                $table->unsignedBigInteger('dress_category_id')->nullable()->after('code');
            }
            if (! Schema::connection($this->connection)->hasColumn('purchase_order_items', 'dress_subcategory_id')) {
                $table->unsignedBigInteger('dress_subcategory_id')->nullable()->after('dress_category_id');
            }
            if (! Schema::connection($this->connection)->hasColumn('purchase_order_items', 'dress_id')) {
                $table->unsignedBigInteger('dress_id')->nullable()->after('dress_subcategory_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('purchase_order_items', function (Blueprint $table): void {
            foreach (['dress_id', 'dress_subcategory_id', 'dress_category_id', 'code'] as $column) {
                if (Schema::connection($this->connection)->hasColumn('purchase_order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::connection($this->connection)->table('purchase_orders', function (Blueprint $table): void {
            foreach (['received_by', 'received_at', 'inventory_received', 'deposit_amount', 'expected_delivery_date'] as $column) {
                if (Schema::connection($this->connection)->hasColumn('purchase_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
