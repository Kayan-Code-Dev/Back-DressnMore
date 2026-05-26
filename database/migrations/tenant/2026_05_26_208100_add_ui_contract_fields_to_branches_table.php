<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('branches', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('branches', 'branch_code')) {
                $table->string('branch_code')->nullable()->after('name');
            }
            if (! Schema::connection($this->connection)->hasColumn('branches', 'vat_enabled')) {
                $table->boolean('vat_enabled')->default(false)->after('phone');
            }
            if (! Schema::connection($this->connection)->hasColumn('branches', 'vat_type')) {
                $table->string('vat_type')->nullable()->after('vat_enabled');
            }
            if (! Schema::connection($this->connection)->hasColumn('branches', 'vat_value')) {
                $table->decimal('vat_value', 12, 2)->nullable()->after('vat_type');
            }
            if (! Schema::connection($this->connection)->hasColumn('branches', 'currency')) {
                $table->string('currency')->nullable()->after('vat_value');
            }
            if (! Schema::connection($this->connection)->hasColumn('branches', 'currency_id')) {
                $table->unsignedBigInteger('currency_id')->nullable()->after('currency');
            }
            if (! Schema::connection($this->connection)->hasColumn('branches', 'street')) {
                $table->string('street')->nullable()->after('currency_id');
            }
            if (! Schema::connection($this->connection)->hasColumn('branches', 'building')) {
                $table->string('building')->nullable()->after('street');
            }
            if (! Schema::connection($this->connection)->hasColumn('branches', 'city_id')) {
                $table->unsignedBigInteger('city_id')->nullable()->after('building');
            }
            if (! Schema::connection($this->connection)->hasColumn('branches', 'notes')) {
                $table->text('notes')->nullable()->after('address');
            }
            if (! Schema::connection($this->connection)->hasColumn('branches', 'inventory_name')) {
                $table->string('inventory_name')->nullable()->after('notes');
            }
            if (! Schema::connection($this->connection)->hasColumn('branches', 'image')) {
                $table->string('image')->nullable()->after('inventory_name');
            }

            $table->index('branch_code');
            $table->index('city_id');
            $table->index('currency_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('branches', function (Blueprint $table): void {
            foreach ([
                'branch_code',
                'vat_enabled',
                'vat_type',
                'vat_value',
                'currency',
                'currency_id',
                'street',
                'building',
                'city_id',
                'notes',
                'inventory_name',
                'image',
            ] as $column) {
                if (Schema::connection($this->connection)->hasColumn('branches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
