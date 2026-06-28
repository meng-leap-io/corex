<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->string('key', 255);
            $table->text('value')->nullable();
            $table->string('type', 30)->default('string');
            $table->string('category', 100)->default('general');
            $table->boolean('is_encrypted')->default(false);
            $table->jsonb('metadata')->default('{}');
            $table->timestampsTz();
            $table->softDeletes();

            $table->index(['key', 'category']);
            $table->index(['user_id', 'key'])->unique()->where('user_id IS NOT NULL');
            $table->index(['team_id', 'key'])->unique()->where('team_id IS NOT NULL');
            $table->index('category');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
