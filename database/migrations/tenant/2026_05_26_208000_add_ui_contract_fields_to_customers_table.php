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
            if (! Schema::connection($this->connection)->hasColumn('customers', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('name');
            }
            if (! Schema::connection($this->connection)->hasColumn('customers', 'source')) {
                $table->string('source')->nullable()->after('national_id');
            }
            if (! Schema::connection($this->connection)->hasColumn('customers', 'phone2')) {
                $table->string('phone2')->nullable()->after('phone');
            }
            if (! Schema::connection($this->connection)->hasColumn('customers', 'city_id')) {
                $table->unsignedBigInteger('city_id')->nullable()->after('address');
            }

            $table->index('date_of_birth');
            $table->index('source');
            $table->index('city_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('customers', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('customers', 'date_of_birth')) {
                $table->dropColumn('date_of_birth');
            }
            if (Schema::connection($this->connection)->hasColumn('customers', 'source')) {
                $table->dropColumn('source');
            }
            if (Schema::connection($this->connection)->hasColumn('customers', 'phone2')) {
                $table->dropColumn('phone2');
            }
            if (Schema::connection($this->connection)->hasColumn('customers', 'city_id')) {
                $table->dropColumn('city_id');
            }
        });
    }
};
