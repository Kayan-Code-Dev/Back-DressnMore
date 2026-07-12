<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('ai_tool_executions', function (Blueprint $table): void {
            $table->id();
            $table->string('tool_name', 64);
            $table->string('tool_version', 16)->default('0.0.0');
            $table->string('status', 16)->default('ok');
            $table->json('facts')->nullable();
            $table->json('scope')->nullable();
            $table->json('warnings')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('execution_ms')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();
            $table->index(['tool_name', 'status']);
            $table->index('executed_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('ai_tool_executions');
    }
};
