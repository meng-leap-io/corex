<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->nullOnDelete();

            $table->text('prompt');
            $table->longText('code_generated');
            $table->string('language', 100)->nullable();

            $table->string('model_used', 100)->nullable();
            $table->integer('tokens_used')->default(0);

            $table->decimal('cost', 10, 6)->default(0);
            $table->string('status', 20)->default('completed');

            $table->timestampsTz();

            $table->index(['user_id', 'created_at']);
            $table->index('project_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_generations');
    }
};
