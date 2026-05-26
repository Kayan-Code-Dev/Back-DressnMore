<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('dresses', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('dresses', 'entity_type')) {
                $table->string('entity_type')->nullable()->after('branch_id');
            }
            if (! Schema::connection($this->connection)->hasColumn('dresses', 'entity_id')) {
                $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');
            }
            if (! Schema::connection($this->connection)->hasColumn('dresses', 'breast_size')) {
                $table->string('breast_size')->nullable()->after('size');
            }
            if (! Schema::connection($this->connection)->hasColumn('dresses', 'waist_size')) {
                $table->string('waist_size')->nullable()->after('breast_size');
            }
            if (! Schema::connection($this->connection)->hasColumn('dresses', 'sleeve_size')) {
                $table->string('sleeve_size')->nullable()->after('waist_size');
            }
            if (! Schema::connection($this->connection)->hasColumn('dresses', 'measurements')) {
                $table->json('measurements')->nullable()->after('sleeve_size');
            }
            if (! Schema::connection($this->connection)->hasColumn('dresses', 'delivery_date')) {
                $table->date('delivery_date')->nullable()->after('measurements');
            }
            if (! Schema::connection($this->connection)->hasColumn('dresses', 'days_of_rent')) {
                $table->unsignedInteger('days_of_rent')->nullable()->after('delivery_date');
            }
            if (! Schema::connection($this->connection)->hasColumn('dresses', 'occasion_datetime')) {
                $table->dateTime('occasion_datetime')->nullable()->after('days_of_rent');
            }
            if (! Schema::connection($this->connection)->hasColumn('dresses', 'visit_datetime')) {
                $table->dateTime('visit_datetime')->nullable()->after('occasion_datetime');
            }

            $table->index(['entity_type', 'entity_id']);
            $table->index('delivery_date');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('dresses', function (Blueprint $table): void {
            foreach ([
                'entity_type',
                'entity_id',
                'breast_size',
                'waist_size',
                'sleeve_size',
                'measurements',
                'delivery_date',
                'days_of_rent',
                'occasion_datetime',
                'visit_datetime',
            ] as $column) {
                if (Schema::connection($this->connection)->hasColumn('dresses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
