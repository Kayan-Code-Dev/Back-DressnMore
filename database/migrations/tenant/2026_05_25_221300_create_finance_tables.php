<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('status')->default('draft');
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->decimal('remaining', 12, 2)->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('dress_id')->nullable()->constrained('dresses')->nullOnDelete();
            $table->string('description')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method')->nullable();
            $table->string('status')->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('category')->nullable();
            $table->decimal('amount', 12, 2);
            $table->text('description')->nullable();
            $table->timestamp('incurred_at')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('delivery_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->string('received_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('delivery_records');
        Schema::connection($this->connection)->dropIfExists('expenses');
        Schema::connection($this->connection)->dropIfExists('payments');
        Schema::connection($this->connection)->dropIfExists('invoice_items');
        Schema::connection($this->connection)->dropIfExists('invoices');
    }
};
