<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('dresses', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('size')->nullable();
            $table->string('color')->nullable();
            $table->string('status')->default('available');
            $table->decimal('rental_price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dress_id')->constrained('dresses')->cascadeOnDelete();
            $table->string('movement_type');
            $table->integer('quantity')->default(1);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('inventory_movements');
        Schema::connection($this->connection)->dropIfExists('dresses');
    }
};
