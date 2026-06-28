<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('bucket', 100);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 127)->nullable();
            $table->bigInteger('size')->default(0);
            $table->string('extension', 50)->nullable();
            $table->string('url', 1000)->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->string('disk', 50)->default('supabase');
            $table->timestampsTz();
            $table->softDeletes();

            $table->index('bucket');
            $table->index('mime_type');
            $table->index(['bucket', 'user_id']);
            $table->index(['user_id', 'bucket']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
