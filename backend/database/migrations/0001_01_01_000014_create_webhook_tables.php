<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('url', 1000);
            $table->string('secret', 500)->nullable();
            $table->jsonb('events')->default('[]');
            $table->string('status', 20)->default('active');
            $table->integer('retry_count')->default(3);
            $table->integer('timeout_seconds')->default(10);
            $table->jsonb('headers')->default('{}');
            $table->jsonb('metadata')->default('{}');
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('last_failure_at')->nullable();
            $table->timestampsTz();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 100)->nullable();
            $table->string('event_type', 255);
            $table->string('event_id', 255)->nullable();
            $table->foreignUuid('endpoint_id')->nullable()->constrained('webhook_endpoints')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->jsonb('payload');
            $table->jsonb('headers')->nullable();
            $table->jsonb('response')->nullable();
            $table->integer('response_status')->nullable();
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->text('error_message')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampsTz();

            $table->index(['provider', 'status']);
            $table->index(['event_type', 'status']);
            $table->index('event_id');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('webhook_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('path', 255)->unique();
            $table->string('handler', 500);
            $table->string('method', 20)->default('POST');
            $table->string('description', 500)->nullable();
            $table->boolean('verify_signature')->default(true);
            $table->boolean('rate_limit')->default(true);
            $table->integer('rate_limit_per_minute')->default(60);
            $table->string('status', 20)->default('active');
            $table->jsonb('middleware')->default('[]');
            $table->timestampsTz();
            $table->softDeletes();

            $table->index('status');
            $table->index('path');
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('log_id')->constrained('webhook_logs')->cascadeOnDelete();
            $table->string('event_type', 255);
            $table->string('status', 20)->default('pending');
            $table->jsonb('data')->nullable();
            $table->jsonb('result')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->index(['log_id', 'status']);
            $table->index('event_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('webhook_routes');
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('webhook_endpoints');
    }
};
