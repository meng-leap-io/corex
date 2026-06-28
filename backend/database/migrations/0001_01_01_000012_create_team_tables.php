<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('plan', 50)->default('free');
            $table->integer('max_members')->default(10);
            $table->jsonb('settings')->default('{}');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('owner_id');
            $table->index('slug');
            $table->index('plan');
        });

        Schema::create('team_user', function (Blueprint $table) {
            $table->foreignUuid('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 30)->default('member');
            $table->jsonb('permissions')->default('{}');
            $table->timestampTz('joined_at')->useCurrent();
            $table->timestampsTz();

            $table->primary(['team_id', 'user_id']);
            $table->index('user_id');
            $table->index('role');
        });

        Schema::create('project_user', function (Blueprint $table) {
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 30)->default('member');
            $table->jsonb('permissions')->default('{}');
            $table->timestampTz('joined_at')->useCurrent();
            $table->timestampsTz();

            $table->primary(['project_id', 'user_id']);
            $table->index('user_id');
            $table->index('role');
        });

        Schema::create('project_team', function (Blueprint $table) {
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('access_level', 30)->default('member');

            $table->primary(['project_id', 'team_id']);
            $table->index('team_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('user')->after('plan');
            $table->index('role');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('visibility', 20)->default('private')->after('status');
            $table->boolean('is_public')->default(false)->after('visibility');
            $table->index('visibility');
            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['is_public']);
            $table->dropIndex(['visibility']);
            $table->dropColumn(['is_public', 'visibility']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });

        Schema::dropIfExists('project_team');
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');
    }
};
