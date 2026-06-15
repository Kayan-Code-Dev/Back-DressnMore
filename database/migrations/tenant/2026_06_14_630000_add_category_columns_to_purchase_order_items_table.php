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
            if (! Schema::connection($this->connection)->hasColumn('purchase_order_items', 'dress_category_id')) {
                $table->unsignedBigInteger('dress_category_id')->nullable()->after('total');
            }
            if (! Schema::connection($this->connection)->hasColumn('purchase_order_items', 'dress_subcategory_id')) {
                $table->unsignedBigInteger('dress_subcategory_id')->nullable()->after('dress_category_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('purchase_order_items', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('purchase_order_items', 'dress_subcategory_id')) {
                $table->dropColumn('dress_subcategory_id');
            }
            if (Schema::connection($this->connection)->hasColumn('purchase_order_items', 'dress_category_id')) {
                $table->dropColumn('dress_category_id');
            }
        });
    }
};
