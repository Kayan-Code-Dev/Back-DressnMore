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
            $table->string('currency', 10)->default('SAR')->after('price');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('plans', function (Blueprint $table): void {
            $table->dropColumn('currency');
        });
    }
};

