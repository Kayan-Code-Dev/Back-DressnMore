<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('invoices', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('invoices', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('customer_id');
            }
            if (! Schema::connection($this->connection)->hasColumn('invoices', 'visit_datetime')) {
                $table->dateTime('visit_datetime')->nullable()->after('tailoring_due_date');
            }
            if (! Schema::connection($this->connection)->hasColumn('invoices', 'occasion_datetime')) {
                $table->dateTime('occasion_datetime')->nullable()->after('visit_datetime');
            }
            if (! Schema::connection($this->connection)->hasColumn('invoices', 'days_of_rent')) {
                $table->unsignedInteger('days_of_rent')->nullable()->after('occasion_datetime');
            }
            if (! Schema::connection($this->connection)->hasColumn('invoices', 'discount_type')) {
                $table->string('discount_type')->nullable()->after('discount');
            }
            if (! Schema::connection($this->connection)->hasColumn('invoices', 'discount_value')) {
                $table->decimal('discount_value', 12, 2)->nullable()->after('discount_type');
            }
            if (! Schema::connection($this->connection)->hasColumn('invoices', 'order_notes')) {
                $table->text('order_notes')->nullable()->after('notes');
            }

            $table->index('branch_id');
            $table->index('visit_datetime');
            $table->index('occasion_datetime');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('invoices', function (Blueprint $table): void {
            foreach ([
                'branch_id',
                'visit_datetime',
                'occasion_datetime',
                'days_of_rent',
                'discount_type',
                'discount_value',
                'order_notes',
            ] as $column) {
                if (Schema::connection($this->connection)->hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
