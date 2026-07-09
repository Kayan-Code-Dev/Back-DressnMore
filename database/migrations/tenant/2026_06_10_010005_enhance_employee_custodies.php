<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('employee_custodies', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_custodies', 'returned_at')) {
                $table->timestamp('returned_at')->nullable()->after('expires_at');
            }
            if (!Schema::hasColumn('employee_custodies', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }
        });
    }
    public function down(): void {
        Schema::table('employee_custodies', function (Blueprint $table) {
            $table->dropColumn(['returned_at', 'notes']);
        });
    }
};
