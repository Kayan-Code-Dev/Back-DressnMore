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
            if (! Schema::connection($this->connection)->hasColumn('purchase_orders', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('supplier_id');
            }
            if (! Schema::connection($this->connection)->hasColumn('purchase_orders', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->after('branch_id');
            }
            if (! Schema::connection($this->connection)->hasColumn('purchase_orders', 'subcategory_id')) {
                $table->unsignedBigInteger('subcategory_id')->nullable()->after('category_id');
            }
            if (! Schema::connection($this->connection)->hasColumn('purchase_orders', 'type')) {
                $table->string('type')->nullable()->after('status');
            }
            if (! Schema::connection($this->connection)->hasColumn('purchase_orders', 'is_returned')) {
                $table->boolean('is_returned')->default(false)->after('type');
            }
            if (! Schema::connection($this->connection)->hasColumn('purchase_orders', 'returned_at')) {
                $table->dateTime('returned_at')->nullable()->after('is_returned');
            }
            if (! Schema::connection($this->connection)->hasColumn('purchase_orders', 'return_notes')) {
                $table->text('return_notes')->nullable()->after('returned_at');
            }

            $table->index('branch_id');
            $table->index('category_id');
            $table->index('subcategory_id');
            $table->index('is_returned');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('purchase_orders', function (Blueprint $table): void {
            foreach ([
                'branch_id',
                'category_id',
                'subcategory_id',
                'type',
                'is_returned',
                'returned_at',
                'return_notes',
            ] as $column) {
                if (Schema::connection($this->connection)->hasColumn('purchase_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
