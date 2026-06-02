<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection($this->connection)->table('personal_access_tokens', function (Blueprint $table): void {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('tokenable_id')
                ->constrained('tenants')
                ->nullOnDelete();
            $table->index(['tenant_id', 'tokenable_type']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
