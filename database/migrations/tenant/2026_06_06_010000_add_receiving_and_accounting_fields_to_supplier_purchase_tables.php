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
            if (! Schema::connection($this->connection)->hasColumn('purchase_orders', 'is_received')) {
                $table->boolean('is_received')->default(false)->after('is_returned');
            }
            if (! Schema::connection($this->connection)->hasColumn('purchase_orders', 'received_at')) {
                $table->dateTime('received_at')->nullable()->after('returned_at');
            }
            if (! Schema::connection($this->connection)->hasColumn('purchase_orders', 'receive_notes')) {
                $table->text('receive_notes')->nullable()->after('return_notes');
            }
            if (! Schema::connection($this->connection)->hasColumn('purchase_orders', 'received_by')) {
                $table->unsignedBigInteger('received_by')->nullable()->after('created_by');
            }

            $table->index('is_received');
            $table->index('received_at');
        });

        Schema::connection($this->connection)->table('supplier_payments', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('supplier_payments', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('purchase_order_id');
            }
            if (! Schema::connection($this->connection)->hasColumn('supplier_payments', 'cashbox_id')) {
                $table->unsignedBigInteger('cashbox_id')->nullable()->after('branch_id');
            }
            if (! Schema::connection($this->connection)->hasColumn('supplier_payments', 'expense_id')) {
                $table->unsignedBigInteger('expense_id')->nullable()->after('cashbox_id');
            }

            $table->index('branch_id');
            $table->index('cashbox_id');
            $table->index('expense_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('supplier_payments', function (Blueprint $table): void {
            foreach (['branch_id', 'cashbox_id', 'expense_id'] as $column) {
                if (Schema::connection($this->connection)->hasColumn('supplier_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::connection($this->connection)->table('purchase_orders', function (Blueprint $table): void {
            foreach (['is_received', 'received_at', 'receive_notes', 'received_by'] as $column) {
                if (Schema::connection($this->connection)->hasColumn('purchase_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
