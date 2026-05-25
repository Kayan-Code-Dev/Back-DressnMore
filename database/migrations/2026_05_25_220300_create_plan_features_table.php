<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection($this->connection)->create('plan_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('feature_key');
            $table->string('feature_value')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('plan_features');
    }
};
