<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TenantAuthService selects permissions.display_name, but the column was
     * only ever added manually to older tenant databases. Freshly provisioned
     * tenants were created without it, breaking login. Guarded so it is a
     * no-op on databases that already have the column.
     */
    public function up(): void
    {
        if (Schema::connection('tenant')->hasColumn('permissions', 'display_name')) {
            return;
        }

        Schema::connection('tenant')->table('permissions', function (Blueprint $table): void {
            $table->string('display_name')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        if (! Schema::connection('tenant')->hasColumn('permissions', 'display_name')) {
            return;
        }

        Schema::connection('tenant')->table('permissions', function (Blueprint $table): void {
            $table->dropColumn('display_name');
        });
    }
};
