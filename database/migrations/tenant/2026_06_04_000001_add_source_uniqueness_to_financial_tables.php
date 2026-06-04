<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('journal_entries', function ($table): void {
            $table->unique(['source_type', 'source_id'], 'journal_entries_source_unique');
        });

        Schema::connection($this->connection)->table('cash_movements', function ($table): void {
            $table->unique(['reference_type', 'reference_id'], 'cash_movements_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('cash_movements', function ($table): void {
            $table->dropUnique('cash_movements_reference_unique');
        });

        Schema::connection($this->connection)->table('journal_entries', function ($table): void {
            $table->dropUnique('journal_entries_source_unique');
        });
    }
};
