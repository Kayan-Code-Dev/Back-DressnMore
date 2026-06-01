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
            if (! Schema::connection($this->connection)->hasColumn('invoices', 'deposit_paid_amount')) {
                $table->decimal('deposit_paid_amount', 12, 2)->default(0)->after('security_deposit_status');
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('invoices', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('invoices', 'deposit_paid_amount')) {
                $table->dropColumn('deposit_paid_amount');
            }
        });
    }
};
