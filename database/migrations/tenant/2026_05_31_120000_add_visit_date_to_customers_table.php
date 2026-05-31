<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('customers', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('customers', 'visit_date')) {
                $table->date('visit_date')->nullable()->after('date_of_birth');
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('customers', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('customers', 'visit_date')) {
                $table->dropColumn('visit_date');
            }
        });
    }
};
