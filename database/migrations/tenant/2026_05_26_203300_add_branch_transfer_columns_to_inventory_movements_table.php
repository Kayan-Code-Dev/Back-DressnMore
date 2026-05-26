<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('inventory_movements', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('inventory_movements', 'from_branch_id')) {
                $table->unsignedBigInteger('from_branch_id')->nullable()->after('dress_id');
                $table->index('from_branch_id');
            }

            if (! Schema::connection($this->connection)->hasColumn('inventory_movements', 'to_branch_id')) {
                $table->unsignedBigInteger('to_branch_id')->nullable()->after('from_branch_id');
                $table->index('to_branch_id');
            }
        });

        $driver = Schema::connection($this->connection)->getConnection()->getDriverName();
        if ($driver !== 'sqlite') {
            Schema::connection($this->connection)->table('inventory_movements', function (Blueprint $table): void {
                $table->foreign('from_branch_id')
                    ->references('id')
                    ->on('branches')
                    ->nullOnDelete();

                $table->foreign('to_branch_id')
                    ->references('id')
                    ->on('branches')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::connection($this->connection)->getConnection()->getDriverName();
        Schema::connection($this->connection)->table('inventory_movements', function (Blueprint $table) use ($driver): void {
            if (Schema::connection($this->connection)->hasColumn('inventory_movements', 'to_branch_id')) {
                if ($driver !== 'sqlite') {
                    $table->dropForeign(['to_branch_id']);
                }
                $table->dropIndex(['to_branch_id']);
                $table->dropColumn('to_branch_id');
            }

            if (Schema::connection($this->connection)->hasColumn('inventory_movements', 'from_branch_id')) {
                if ($driver !== 'sqlite') {
                    $table->dropForeign(['from_branch_id']);
                }
                $table->dropIndex(['from_branch_id']);
                $table->dropColumn('from_branch_id');
            }
        });
    }
};
