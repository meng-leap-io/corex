<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 20);
            $table->longText('content');
            $table->string('model_used', 100)->nullable();
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->integer('total_tokens')->default(0)->index();
            $table->decimal('cost', 10, 6)->default(0);
            $table->jsonb('metadata')->default('{}');
            $table->integer('sequence')->default(0);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['conversation_id', 'sequence']);
            $table->index(['conversation_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('role');
            $table->index('created_at');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('messages');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->jsonb('messages')->default('[]');
        });

        Schema::dropIfExists('messages');
    }
};
