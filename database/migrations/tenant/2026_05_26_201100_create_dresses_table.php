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
            $table->foreignId('dress_category_id')
                ->nullable()
                ->constrained('dress_categories')
                ->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('size')->nullable();
            $table->string('color')->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->decimal('rental_price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->string('status')->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('status');
            $table->index('color');
            $table->index('size');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('dresses');
    }
};
