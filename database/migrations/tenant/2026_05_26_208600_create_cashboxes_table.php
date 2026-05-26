<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('cashboxes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->decimal('initial_balance', 12, 2)->default(0);
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('branch_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('cashboxes');
    }
};
