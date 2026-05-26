<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('suppliers', function (Blueprint $table): void {
            if (! Schema::connection($this->connection)->hasColumn('suppliers', 'code')) {
                $table->string('code')->nullable()->after('id');
            }

            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('suppliers', function (Blueprint $table): void {
            if (Schema::connection($this->connection)->hasColumn('suppliers', 'code')) {
                $table->dropColumn('code');
            }
        });
    }
};
