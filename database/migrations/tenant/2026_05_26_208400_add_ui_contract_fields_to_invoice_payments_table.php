<?php

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
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
            if (! Schema::connection($this->connection)->hasColumn('invoice_payments', 'status')) {
                $table->string('status')->default(PaymentStatus::PAID->value)->after('amount');
            }
            if (! Schema::connection($this->connection)->hasColumn('invoice_payments', 'payment_type')) {
                $table->string('payment_type')->default(PaymentType::INVOICE_PAYMENT->value)->after('status');
            }
            if (! Schema::connection($this->connection)->hasColumn('invoice_payments', 'cancelled_at')) {
                $table->dateTime('cancelled_at')->nullable()->after('paid_at');
            }

            $table->index('status');
            $table->index('payment_type');
        });

        DB::connection($this->connection)
            ->table('invoice_payments')
            ->whereNull('status')
            ->update(['status' => PaymentStatus::PAID->value]);

        DB::connection($this->connection)
            ->table('invoice_payments')
            ->whereNull('payment_type')
            ->update(['payment_type' => PaymentType::INVOICE_PAYMENT->value]);
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('invoice_payments', function (Blueprint $table): void {
            foreach (['status', 'payment_type', 'cancelled_at'] as $column) {
                if (Schema::connection($this->connection)->hasColumn('invoice_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
