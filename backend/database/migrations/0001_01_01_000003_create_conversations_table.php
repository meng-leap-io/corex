<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();

            $table->string('title', 500)->nullable();
            $table->string('model_used', 100)->nullable();

            $table->jsonb('messages')->default('[]');
            $table->integer('tokens_used')->default(0);

            $table->decimal('total_cost', 10, 6)->default(0);

            $table->timestampsTz();

            $table->index(['user_id', 'created_at']);
            $table->index('project_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
