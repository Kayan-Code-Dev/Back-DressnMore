<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('super_admin_id')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('category')->default('system');
            $table->string('priority')->default('normal');
            $table->timestamp('read_at')->nullable();
            $table->string('action_url')->nullable();
            $table->timestamps();

            $table->index(['super_admin_id', 'read_at']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_notifications');
    }
};
