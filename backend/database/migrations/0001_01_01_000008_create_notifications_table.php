<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->jsonb('data')->default('{}');
            $table->string('priority', 20)->default('normal');
            $table->string('channel', 100)->nullable();
            $table->string('event', 100)->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('dismissed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletes();

            $table->index('type');
            $table->index('priority');
            $table->index('read_at');
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
