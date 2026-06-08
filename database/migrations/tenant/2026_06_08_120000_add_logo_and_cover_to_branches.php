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
            if (! Schema::connection($this->connection)->hasColumn('branches', 'logo')) {
                $table->string('logo')->nullable()->after('image');
            }
            if (! Schema::connection($this->connection)->hasColumn('branches', 'cover')) {
                $table->string('cover')->nullable()->after('logo');
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('branches', function (Blueprint $table): void {
            foreach (['logo', 'cover'] as $column) {
                if (Schema::connection($this->connection)->hasColumn('branches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
