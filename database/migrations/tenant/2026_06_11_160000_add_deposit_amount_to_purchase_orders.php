<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('purchase_orders', function (Blueprint $table): void {
            $table->decimal('deposit_amount', 12, 2)->default(0)->after('remaining_amount');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('purchase_orders', function (Blueprint $table): void {
            $table->dropColumn('deposit_amount');
        });
    }
};

