<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection($this->connection)->create('payment_gateways', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('account_holder');
            $table->string('account_number');
            $table->string('bank_name')->nullable();
            $table->string('iban')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(1);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
        });

        Schema::connection($this->connection)->table('payments', function (Blueprint $table): void {
            $table->foreignId('plan_id')->nullable()->after('tenant_id')->constrained('plans')->nullOnDelete();
            $table->foreignId('payment_gateway_id')->nullable()->after('plan_id')->constrained('payment_gateways')->nullOnDelete();
            $table->string('purpose')->nullable()->after('payment_gateway_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_gateway_id');
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn('purpose');
        });

        Schema::connection($this->connection)->dropIfExists('payment_gateways');
    }
};
