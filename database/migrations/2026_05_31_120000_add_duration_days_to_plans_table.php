<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection($this->connection)->table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('duration_days')->default(365)->after('billing_cycle');
            $table->unsignedInteger('sort_order')->default(0)->after('duration_days');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('plans', function (Blueprint $table): void {
            $table->dropColumn(['duration_days', 'sort_order']);
        });
    }
};
