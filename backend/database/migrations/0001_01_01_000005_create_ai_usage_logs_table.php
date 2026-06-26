<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('provider', 100);
            $table->string('model', 100);

            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);

            $table->decimal('cost', 10, 6)->default(0);
            $table->integer('duration')->nullable();

            $table->string('endpoint', 255)->nullable();
            $table->boolean('success')->default(true);

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestampsTz();

            $table->index(['user_id', 'created_at']);
            $table->index(['provider', 'model']);
            $table->index('created_at');
            $table->index('success');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
