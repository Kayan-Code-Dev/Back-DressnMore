<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dress_id')
                ->nullable()
                ->constrained('dresses')
                ->nullOnDelete();
            $table->string('type');
            $table->integer('quantity')->default(1);
            $table->string('reason')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('created_by');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('inventory_movements');
    }
};
