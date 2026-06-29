<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('invoice_payments', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('invoice_payments', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('invoice_id');
            }
            if (! Schema::connection($this->connection)->hasColumn('invoice_payments', 'cashbox_id')) {
                $table->unsignedBigInteger('cashbox_id')->nullable()->after('branch_id');
            }
        });

        $driver = Schema::connection($this->connection)->getConnection()->getDriverName();
        if ($driver === 'mysql') {
            Schema::connection($this->connection)->table('invoice_payments', function (Blueprint $table): void {
                $table->dropForeign(['invoice_id']);
            });
            DB::connection($this->connection)->statement(
                'ALTER TABLE invoice_payments MODIFY invoice_id BIGINT UNSIGNED NULL'
            );
            Schema::connection($this->connection)->table('invoice_payments', function (Blueprint $table): void {
                $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
                $table->index('branch_id');
                $table->index('cashbox_id');
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('invoice_payments', function (Blueprint $table): void {
            foreach (['branch_id', 'cashbox_id'] as $column) {
                if (Schema::connection($this->connection)->hasColumn('invoice_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
