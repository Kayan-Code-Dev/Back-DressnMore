<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('users', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('users', 'avatar_path')) {
                $table->string('avatar_path')->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('users', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('users', 'avatar_path')) {
                $table->dropColumn('avatar_path');
            }
        });
    }
};
