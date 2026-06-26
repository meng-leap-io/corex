<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();

            $table->string('avatar')->nullable();
            $table->string('github_id')->nullable()->unique();
            $table->string('google_id')->nullable()->unique();
            $table->string('provider')->nullable();
            $table->string('provider_id')->nullable();

            $table->string('plan')->default('free');
            $table->timestampTz('plan_expires_at')->nullable();

            $table->integer('api_usage_limit')->default(1000);
            $table->integer('api_usage_current')->default(0);

            $table->jsonb('settings')->default('{}');
            $table->jsonb('preferences')->default('{}');

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('created_at');
            $table->index('plan');
            $table->index('provider');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index()->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
