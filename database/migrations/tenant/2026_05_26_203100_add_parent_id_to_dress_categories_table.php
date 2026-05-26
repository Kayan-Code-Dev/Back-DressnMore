<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasColumn('dress_categories', 'parent_id')) {
            Schema::connection($this->connection)->table('dress_categories', function (Blueprint $table): void {
                $table->unsignedBigInteger('parent_id')->nullable()->after('id');
                $table->index('parent_id');
            });
        }

        $driver = Schema::connection($this->connection)->getConnection()->getDriverName();
        if ($driver !== 'sqlite') {
            Schema::connection($this->connection)->table('dress_categories', function (Blueprint $table): void {
                $table->foreign('parent_id')
                    ->references('id')
                    ->on('dress_categories')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasColumn('dress_categories', 'parent_id')) {
            $driver = Schema::connection($this->connection)->getConnection()->getDriverName();
            Schema::connection($this->connection)->table('dress_categories', function (Blueprint $table) use ($driver): void {
                if ($driver !== 'sqlite') {
                    $table->dropForeign(['parent_id']);
                }
                $table->dropIndex(['parent_id']);
                $table->dropColumn('parent_id');
            });
        }
    }
};
