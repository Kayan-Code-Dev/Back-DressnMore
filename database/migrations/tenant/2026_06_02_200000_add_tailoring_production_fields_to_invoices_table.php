<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('invoices', function (Blueprint $table): void {
            $table->string('production_stage', 40)->default('new_order')->after('tailoring_notes');
            $table->string('production_status', 40)->default('pending')->after('production_stage');
            $table->string('priority', 20)->default('normal')->after('production_status');
            $table->unsignedBigInteger('assigned_tailor_id')->nullable()->after('priority');
            $table->date('fitting_date')->nullable()->after('assigned_tailor_id');
            $table->date('next_follow_up_date')->nullable()->after('fitting_date');
            $table->json('tailoring_measurements')->nullable()->after('next_follow_up_date');
            $table->text('design_notes')->nullable()->after('tailoring_measurements');
            $table->text('workshop_notes')->nullable()->after('design_notes');
            $table->timestamp('tailoring_started_at')->nullable()->after('workshop_notes');
            $table->timestamp('tailoring_completed_at')->nullable()->after('tailoring_started_at');
            $table->timestamp('tailoring_cancelled_at')->nullable()->after('tailoring_completed_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'production_stage',
                'production_status',
                'priority',
                'assigned_tailor_id',
                'fitting_date',
                'next_follow_up_date',
                'tailoring_measurements',
                'design_notes',
                'workshop_notes',
                'tailoring_started_at',
                'tailoring_completed_at',
                'tailoring_cancelled_at',
            ]);
        });
    }
};
