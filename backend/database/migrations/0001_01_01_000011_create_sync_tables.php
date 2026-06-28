<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_status', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('table_name', 100);
            $table->uuid('record_id');
            $table->uuid('user_id')->nullable();
            $table->string('action', 20)->default('upsert');
            $table->string('status', 20)->default('pending');
            $table->integer('version')->default(0);
            $table->text('error_message')->nullable();
            $table->timestampTz('synced_at')->nullable();
            $table->timestampsTz();
            $table->softDeletes();

            $table->index(['table_name', 'record_id']);
            $table->index(['table_name', 'status']);
            $table->index('user_id');
            $table->index('status');
        });

        Schema::create('sync_queue', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('job_id');
            $table->string('table_name', 100);
            $table->uuid('record_id');
            $table->uuid('user_id')->nullable();
            $table->string('action', 20)->default('upsert');
            $table->jsonb('data')->nullable();
            $table->integer('priority')->default(0);
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletes();

            $table->index(['table_name', 'record_id']);
            $table->index(['table_name', 'action']);
            $table->index('status');
            $table->index('priority');
            $table->index('user_id');
            $table->index(['status', 'priority', 'scheduled_at']);
        });

        Schema::create('sync_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('table_name', 100);
            $table->uuid('record_id');
            $table->uuid('user_id')->nullable();
            $table->jsonb('data');
            $table->integer('version')->default(0);
            $table->string('reason', 50)->default('sync');
            $table->timestampTz('restored_at')->nullable();
            $table->timestampsTz();
            $table->softDeletes();

            $table->index(['table_name', 'record_id']);
            $table->index(['table_name', 'record_id', 'version']);
            $table->index('reason');
            $table->index('user_id');
            $table->index('created_at');
        });

        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('table_name', 100);
            $table->uuid('record_id');
            $table->uuid('user_id')->nullable();
            $table->integer('local_version')->default(0);
            $table->integer('remote_version')->default(0);
            $table->jsonb('local_data');
            $table->jsonb('remote_data');
            $table->jsonb('diff')->nullable();
            $table->jsonb('resolution_data')->nullable();
            $table->text('reason')->nullable();
            $table->string('strategy', 30)->default('manual');
            $table->string('status', 20)->default('pending');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
            $table->softDeletes();

            $table->index(['table_name', 'record_id']);
            $table->index(['table_name', 'status']);
            $table->index('status');
            $table->index('user_id');
            $table->index('strategy');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
        Schema::dropIfExists('sync_snapshots');
        Schema::dropIfExists('sync_queue');
        Schema::dropIfExists('sync_status');
    }
};
