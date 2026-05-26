<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('dresses', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('dresses', 'dress_subcategory_id')) {
                $table->unsignedBigInteger('dress_subcategory_id')->nullable()->after('dress_category_id');
                $table->index('dress_subcategory_id');
            }

            if (! Schema::connection($this->connection)->hasColumn('dresses', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('dress_subcategory_id');
                $table->index('branch_id');
            }
        });

        $driver = Schema::connection($this->connection)->getConnection()->getDriverName();
        if ($driver !== 'sqlite') {
            Schema::connection($this->connection)->table('dresses', function (Blueprint $table): void {
                $table->foreign('dress_subcategory_id')
                    ->references('id')
                    ->on('dress_categories')
                    ->nullOnDelete();

                $table->foreign('branch_id')
                    ->references('id')
                    ->on('branches')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::connection($this->connection)->getConnection()->getDriverName();
        Schema::connection($this->connection)->table('dresses', function (Blueprint $table) use ($driver): void {
            if (Schema::connection($this->connection)->hasColumn('dresses', 'branch_id')) {
                if ($driver !== 'sqlite') {
                    $table->dropForeign(['branch_id']);
                }
                $table->dropIndex(['branch_id']);
                $table->dropColumn('branch_id');
            }

            if (Schema::connection($this->connection)->hasColumn('dresses', 'dress_subcategory_id')) {
                if ($driver !== 'sqlite') {
                    $table->dropForeign(['dress_subcategory_id']);
                }
                $table->dropIndex(['dress_subcategory_id']);
                $table->dropColumn('dress_subcategory_id');
            }
        });
    }
};
