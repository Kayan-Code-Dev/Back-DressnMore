<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection($this->connection)->create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('database_name')->unique();
            $table->string('status')->default('provisioning');
            $table->string('owner_name');
            $table->string('owner_email');
            $table->timestamps();

            $table->index(['status', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('tenants');
    }
};
