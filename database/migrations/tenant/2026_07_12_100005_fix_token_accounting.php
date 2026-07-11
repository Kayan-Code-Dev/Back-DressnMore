<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->unsignedInteger('input_tokens')->nullable()->after('tokens_used');
            $table->unsignedInteger('output_tokens')->nullable()->after('input_tokens');
            $table->renameColumn('tokens_used', 'total_tokens');
        });

        Schema::table('ai_runs', function (Blueprint $table) {
            $table->unsignedInteger('input_tokens')->nullable()->after('tokens_used');
            $table->unsignedInteger('output_tokens')->nullable()->after('input_tokens');
            $table->renameColumn('tokens_used', 'total_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->renameColumn('total_tokens', 'tokens_used');
            $table->dropColumn(['input_tokens', 'output_tokens']);
        });

        Schema::table('ai_runs', function (Blueprint $table) {
            $table->renameColumn('total_tokens', 'tokens_used');
            $table->dropColumn(['input_tokens', 'output_tokens']);
        });
    }
};
