<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('slug', 255)->unique();

            $table->string('language', 100)->nullable();
            $table->string('framework', 100)->nullable();

            $table->jsonb('files')->default('[]');
            $table->jsonb('structure')->default('{}');

            $table->string('status', 20)->default('draft');

            $table->timestampTz('last_accessed_at')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
