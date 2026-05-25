<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('role_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role_id', 'user_id']);
        });

        Schema::connection($this->connection)->create('permission_role', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('permission_role');
        Schema::connection($this->connection)->dropIfExists('role_user');
        Schema::connection($this->connection)->dropIfExists('permissions');
        Schema::connection($this->connection)->dropIfExists('roles');
        Schema::connection($this->connection)->dropIfExists('users');
    }
};
