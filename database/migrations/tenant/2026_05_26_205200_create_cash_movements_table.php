<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->decimal('amount', 12, 2);
            $table->string('method')->nullable();
            $table->string('direction');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference')->nullable();
            $table->dateTime('movement_date')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('direction');
            $table->index(['reference_type', 'reference_id']);
            $table->index('movement_date');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('cash_movements');
    }
};
