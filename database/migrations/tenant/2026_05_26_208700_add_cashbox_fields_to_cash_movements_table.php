<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('cash_movements', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('cash_movements', 'cashbox_id')) {
                $table->unsignedBigInteger('cashbox_id')->nullable()->after('direction');
            }
            if (! Schema::connection($this->connection)->hasColumn('cash_movements', 'balance_after')) {
                $table->decimal('balance_after', 12, 2)->nullable()->after('amount');
            }
            if (! Schema::connection($this->connection)->hasColumn('cash_movements', 'is_reversed')) {
                $table->boolean('is_reversed')->default(false)->after('notes');
            }

            $table->index('cashbox_id');
            $table->index('is_reversed');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('cash_movements', function (Blueprint $table): void {
            foreach (['cashbox_id', 'balance_after', 'is_reversed'] as $column) {
                if (Schema::connection($this->connection)->hasColumn('cash_movements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
