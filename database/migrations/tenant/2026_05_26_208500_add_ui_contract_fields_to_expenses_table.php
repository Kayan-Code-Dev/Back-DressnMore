<?php

use App\Enums\ExpenseWorkflowStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('expenses', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('expenses', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('expense_category_id');
            }
            if (! Schema::connection($this->connection)->hasColumn('expenses', 'cashbox_id')) {
                $table->unsignedBigInteger('cashbox_id')->nullable()->after('branch_id');
            }
            if (! Schema::connection($this->connection)->hasColumn('expenses', 'vendor')) {
                $table->string('vendor')->nullable()->after('method');
            }
            if (! Schema::connection($this->connection)->hasColumn('expenses', 'reference_number')) {
                $table->string('reference_number')->nullable()->after('reference');
            }
            if (! Schema::connection($this->connection)->hasColumn('expenses', 'status')) {
                $table->string('status')->default(ExpenseWorkflowStatus::PAID->value)->after('amount');
            }
            if (! Schema::connection($this->connection)->hasColumn('expenses', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('created_by');
            }
            if (! Schema::connection($this->connection)->hasColumn('expenses', 'paid_at')) {
                $table->dateTime('paid_at')->nullable()->after('approved_by');
            }
            if (! Schema::connection($this->connection)->hasColumn('expenses', 'cancelled_at')) {
                $table->dateTime('cancelled_at')->nullable()->after('paid_at');
            }
            if (! Schema::connection($this->connection)->hasColumn('expenses', 'transaction_id')) {
                $table->string('transaction_id')->nullable()->after('cancelled_at');
            }

            $table->index('branch_id');
            $table->index('cashbox_id');
            $table->index('status');
        });

        DB::connection($this->connection)
            ->table('expenses')
            ->whereNull('status')
            ->update(['status' => ExpenseWorkflowStatus::PAID->value]);
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('expenses', function (Blueprint $table): void {
            foreach ([
                'branch_id',
                'cashbox_id',
                'vendor',
                'reference_number',
                'status',
                'approved_by',
                'paid_at',
                'cancelled_at',
                'transaction_id',
            ] as $column) {
                if (Schema::connection($this->connection)->hasColumn('expenses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
