<?php

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $defaultPlanId = Plan::query()->where('slug', 'basic')->value('id')
            ?? Plan::query()->where('status', 'active')->orderBy('id')->value('id');

        if ($defaultPlanId === null) {
            return;
        }

        Tenant::query()
            ->whereNull('plan_id')
            ->update(['plan_id' => $defaultPlanId]);
    }

    public function down(): void
    {
        // No rollback — tenants keep their assigned plan.
    }
};
