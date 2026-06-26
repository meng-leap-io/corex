<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('name', 255);
            $table->text('key');

            $table->jsonb('permissions')->default('[]');

            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable();

            $table->timestampsTz();

            $table->index('user_id');
            $table->index('expires_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
