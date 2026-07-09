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
            $table->decimal('production_cost', 12, 2)->default(0)->after('tax');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('invoices', function (Blueprint $table): void {
            $table->dropColumn('production_cost');
        });
    }
};
