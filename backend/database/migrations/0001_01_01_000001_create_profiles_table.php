<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();

            $table->text('bio')->nullable();
            $table->string('company', 255)->nullable();
            $table->string('website', 500)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('twitter', 255)->nullable();
            $table->string('github', 255)->nullable();

            $table->jsonb('expertise')->default('[]');
            $table->jsonb('skills')->default('[]');

            $table->string('public_email', 255)->nullable();
            $table->jsonb('notification_settings')->default('{}');

            $table->timestampsTz();

            $table->index('created_at');
            $table->index('location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
